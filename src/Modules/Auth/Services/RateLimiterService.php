<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Config;
use App\Core\Request;
use App\Exceptions\RateLimitException;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Auth\Repositories\LoginAttemptRepository;
use Monolog\Logger;

/**
 * DB-backed brute-force protection service with configurable thresholds and exponential backoff.
 *
 * // [SECURITY:BRUTE-FORCE] Enforces sliding-window per-username and per-IP lockouts,
 * // logs lockout triggers, and never leaks whether an account exists.
 */
final class RateLimiterService
{
    private readonly int $maxAttemptsPerUser;
    private readonly int $maxAttemptsPerIp;
    private readonly int $lockoutSeconds;
    private readonly int $backoffBaseSeconds;
    private readonly int $backoffMaxSeconds;

    /** @var callable(int): void */
    private readonly mixed $sleeper;

    /**
     * @param callable(int): void|null $sleeper Optional sleep callback (no-op in unit tests)
     */
    public function __construct(
        private readonly LoginAttemptRepository $attempts,
        Config $config,
        private readonly Logger $logger,
        private readonly AuditLogger $auditLogger,
        ?callable $sleeper = null
    ) {
        $this->maxAttemptsPerUser = max(1, $config->int('security.brute_force.max_attempts_per_user', 5));
        $this->maxAttemptsPerIp = max(1, $config->int('security.brute_force.max_attempts_per_ip', 15));
        $this->lockoutSeconds = max(60, $config->int('security.brute_force.lockout_seconds', 900));
        $this->backoffBaseSeconds = max(0, $config->int('security.brute_force.backoff_base_seconds', 2));
        $this->backoffMaxSeconds = max(0, $config->int('security.brute_force.backoff_max_seconds', 10));
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };
    }

    /**
     * Check whether either `$username` or `$request->ip()` has exceeded failure thresholds.
     *
     * @throws RateLimitException
     */
    public function assertAllowed(string $username, Request $request): void
    {
        $ip = $request->ip();
        $userFailures = $this->attempts->countRecentFailuresForUsername($username, $this->lockoutSeconds);
        $ipFailures = $this->attempts->countRecentFailuresForIp($ip, $this->lockoutSeconds);

        if ($userFailures >= $this->maxAttemptsPerUser || $ipFailures >= $this->maxAttemptsPerIp) {
            $retryAfter = $this->attempts->remainingLockoutSeconds($username, $ip, $this->lockoutSeconds);

            $this->logger->warning('Authentication rate limit triggered.', [
                'request_id' => $request->requestId(),
                'ip' => $ip,
                'user_failures' => $userFailures,
                'ip_failures' => $ipFailures,
                'retry_after' => $retryAfter,
            ]);

            $this->auditLogger->record(
                'auth.rate_limited',
                null,
                'login_attempts',
                $username,
                $request,
                [
                    'user_failures' => $userFailures,
                    'ip_failures' => $ipFailures,
                    'retry_after' => $retryAfter,
                ]
            );

            throw new RateLimitException(
                $retryAfter,
                'Too many unsuccessful sign-in attempts. Please wait a few minutes before trying again.',
                sprintf('Lockout active for identifier "%s" / IP %s', $username, $ip)
            );
        }
    }

    /**
     * Compute exponential back-off delay in seconds for `$failedAttempts` prior failures.
     */
    public function calculateBackoffSeconds(int $failedAttempts): int
    {
        if ($failedAttempts <= 0 || $this->backoffBaseSeconds <= 0 || $this->backoffMaxSeconds <= 0) {
            return 0;
        }

        $delay = $this->backoffBaseSeconds * (2 ** max(0, $failedAttempts - 1));
        return (int) min($this->backoffMaxSeconds, $delay);
    }

    /**
     * Record a failed login attempt and apply configured exponential back-off delay.
     */
    public function recordFailure(string $username, Request $request): void
    {
        $ip = $request->ip();
        $this->attempts->record($username, $ip, false, $request->userAgent());

        $failures = $this->attempts->countRecentFailuresForUsername($username, $this->lockoutSeconds);
        $delaySeconds = $this->calculateBackoffSeconds($failures);

        $this->logger->warning('Failed login attempt recorded.', [
            'request_id' => $request->requestId(),
            'ip' => $ip,
            'attempt_count' => $failures,
            'backoff_seconds' => $delaySeconds,
        ]);

        if ($delaySeconds > 0) {
            ($this->sleeper)($delaySeconds);
        }
    }

    public function recordSuccess(string $username, Request $request): void
    {
        $this->attempts->record($username, $request->ip(), true, $request->userAgent());
    }
}
