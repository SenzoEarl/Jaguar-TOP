<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-session Cross-Site Request Forgery (CSRF) synchronizer token service.
 *
 * // [SECURITY:CSRF] Generates cryptographically random tokens, validates with
 * // constant-time `hash_equals()`, and rotates tokens on login/privilege change.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token_value';

    public function __construct(
        private readonly Session $session,
        private readonly string $fieldName = '_csrf_token',
        private readonly string $headerName = 'X-CSRF-Token'
    ) {
    }

    /**
     * Return the current session's CSRF token, lazily generating one if absent.
     */
    public function token(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);
        if (is_string($existing) && strlen($existing) === 64) {
            return $existing;
        }

        return $this->rotate();
    }

    /**
     * Force generation of a new 256-bit random token (called on login & logout).
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Verify a submitted token against the session token in constant time.
     *
     * // [SECURITY:CSRF] Uses `hash_equals` to prevent timing side-channels.
     */
    public function validate(?string $submittedToken): bool
    {
        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $submittedToken);
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    public function headerName(): string
    {
        return $this->headerName;
    }
}
