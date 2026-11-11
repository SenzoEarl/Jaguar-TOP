<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\CurrentUserProviderInterface;
use App\Core\Request;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Repositories\UserRepository;

/**
 * Core Authentication Service handling password hashing, login, registration, and logout.
 *
 * // [SECURITY:PASSWORD] Argon2id hashing (bcrypt fallback), password_verify(),
 * // password_needs_rehash() upgrade on login, and constant-time dummy verification
 * // to prevent timing-based username enumeration.
 * // [SECURITY:SESSION] Regenerates session ID on login & destroys cookie on logout.
 * // [SECURITY:CSRF] Rotates CSRF synchronizer token on authentication transitions.
 */
final class AuthService implements CurrentUserProviderInterface
{
    private const SESSION_USER_ID_KEY = 'auth_user_id';

    /** Precomputed Argon2id dummy hash used so unknown-user logins match real verify timing. */
    private const DUMMY_HASH =
        '$argon2id$v=19$m=65536,t=4,p=1$YWFhYWFhYWFhYWFhYWFhYQ$4J3qO2u5S1N6nZ1c7e4k2H5qQ2rR8z5Q3l9yV7kZ9rQ';

    private ?User $cachedCurrentUser = null;
    private bool $currentUserResolved = false;

    public function __construct(
        private readonly UserRepository $users,
        private readonly RateLimiterService $rateLimiter,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly Config $config,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Hash a plaintext password using Argon2id (from .env) with bcrypt fallback.
     *
     * // [SECURITY:PASSWORD]
     */
    public function hashPassword(string $plainPassword): string
    {
        [$algo, $options] = $this->resolveHashingAlgorithm();
        return password_hash($plainPassword, $algo, $options);
    }

    /**
     * Authenticate a user by username or email and establish a hardened session.
     *
     * // [SECURITY:PASSWORD] [SECURITY:SESSION] [SECURITY:CSRF] [SECURITY:BRUTE-FORCE]
     */
    public function attemptLogin(string $identifier, string $plainPassword, Request $request): User
    {
        $normalized = strtolower(trim($identifier));
        $this->rateLimiter->assertAllowed($normalized, $request);

        $user = $this->users->findByUsernameOrEmail($normalized);
        $targetHash = $user !== null ? $user->getPasswordHash() : self::DUMMY_HASH;

        // Always execute password_verify() even when user is missing for constant-time defense.
        $passwordValid = password_verify($plainPassword, $targetHash);

        if ($user === null || !$user->isActive() || !$passwordValid) {
            $this->rateLimiter->recordFailure($normalized, $request);
            $this->auditLogger->record(
                'auth.login.failed',
                $user?->getId(),
                'users',
                $normalized,
                $request,
                ['reason' => $user !== null && !$user->isActive() ? 'inactive' : 'invalid_credentials']
            );

            // Generic failure message prevents username/email enumeration.
            throw new AuthenticationException(
                'Invalid username/email or password.',
                'Failed authentication attempt for identifier: ' . $normalized
            );
        }

        // Transparently upgrade stored hash when algorithm or cost factors change in .env.
        [$algo, $options] = $this->resolveHashingAlgorithm();
        if (password_needs_rehash($user->getPasswordHash(), $algo, $options)) {
            $upgradedHash = password_hash($plainPassword, $algo, $options);
            $this->users->updatePasswordHash($user->getId(), $upgradedHash);
        }

        $this->rateLimiter->recordSuccess($normalized, $request);
        $this->users->touchLastLogin($user->getId());

        // [SECURITY:SESSION] Defend against session fixation by regenerating ID on login.
        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID_KEY, $user->getId());

        // [SECURITY:CSRF] Rotate CSRF token on privilege/session boundary change.
        $this->csrf->rotate();

        $this->cachedCurrentUser = $user;
        $this->currentUserResolved = true;

        $this->auditLogger->record(
            'auth.login.success',
            $user->getId(),
            'users',
            $user->getId(),
            $request,
            ['role' => $user->getRole()->slug(), 'department' => $user->getDepartment()?->value]
        );

        return $user;
    }

    /**
     * Register a new Level-0 Customer account and sign them in immediately.
     */
    public function registerCustomer(
        string $username,
        string $email,
        string $fullName,
        string $plainPassword,
        Request $request
    ): User {
        if ($this->users->existsByUsernameOrEmail($username, $email)) {
            throw new ValidationException([
                'email' => ['An account with this username or email already exists.'],
            ]);
        }

        $hash = $this->hashPassword($plainPassword);
        $user = $this->users->createCustomer($username, $email, $fullName, $hash);

        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID_KEY, $user->getId());
        $this->csrf->rotate();

        $this->cachedCurrentUser = $user;
        $this->currentUserResolved = true;

        $this->auditLogger->record(
            'auth.register',
            $user->getId(),
            'users',
            $user->getId(),
            $request,
            ['username' => $user->getUsername()]
        );

        return $user;
    }

    /**
     * Terminate the authenticated session, clear cookie, and rotate CSRF token.
     *
     * // [SECURITY:SESSION]
     */
    public function logout(Request $request): void
    {
        $user = $this->currentUser();
        $this->auditLogger->record(
            'auth.logout',
            $user?->getId(),
            'users',
            $user?->getId(),
            $request
        );

        $this->session->destroy();
        $this->session->start();
        $this->csrf->rotate();

        $this->cachedCurrentUser = null;
        $this->currentUserResolved = true;
    }

    public function currentUser(): ?AuthenticatedUserInterface
    {
        if ($this->currentUserResolved) {
            return $this->cachedCurrentUser;
        }

        $this->currentUserResolved = true;
        $userId = $this->session->get(self::SESSION_USER_ID_KEY);
        if (!is_int($userId) && !is_numeric($userId)) {
            $this->cachedCurrentUser = null;
            return null;
        }

        $user = $this->users->findById((int) $userId);
        if ($user === null || !$user->isActive()) {
            $this->session->remove(self::SESSION_USER_ID_KEY);
            $this->cachedCurrentUser = null;
            return null;
        }

        $this->cachedCurrentUser = $user;
        return $this->cachedCurrentUser;
    }

    /**
     * Resolve PHP password hashing constant & options from `.env`-backed config.
     *
     * @return array{0: string|int|null, 1: array<string, int>}
     */
    private function resolveHashingAlgorithm(): array
    {
        $preferred = strtolower($this->config->string('security.password.algo', 'argon2id'));

        if ($preferred === 'argon2id' && defined('PASSWORD_ARGON2ID')) {
            return [
                PASSWORD_ARGON2ID,
                [
                    'memory_cost' => max(16384, $this->config->int('security.password.argon2_memory_cost', 65536)),
                    'time_cost' => max(2, $this->config->int('security.password.argon2_time_cost', 4)),
                    'threads' => max(1, $this->config->int('security.password.argon2_threads', 1)),
                ],
            ];
        }

        return [
            PASSWORD_BCRYPT,
            [
                'cost' => max(10, min(14, $this->config->int('security.password.bcrypt_cost', 12))),
            ],
        ];
    }
}
