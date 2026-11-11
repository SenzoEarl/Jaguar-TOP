<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Infrastructure\Database\Connection;

/**
 * Persistence layer for DB-backed authentication throttling (`login_attempts`).
 *
 * // [SECURITY:BRUTE-FORCE] Tracks failures per username and per IP in MariaDB.
 */
final class LoginAttemptRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Record a login attempt outcome for rate-limiting and forensic audit.
     */
    public function record(string $username, string $ipAddress, bool $wasSuccessful, string $userAgent): void
    {
        $this->connection->execute(
            'INSERT INTO login_attempts (username, ip_address, was_successful, user_agent, attempted_at)
             VALUES (:username, :ip_address, :was_successful, :user_agent, :attempted_at)',
            [
                'username' => mb_substr(strtolower(trim($username)), 0, 60),
                'ip_address' => mb_substr($ipAddress, 0, 45),
                'was_successful' => $wasSuccessful ? 1 : 0,
                'user_agent' => mb_substr($userAgent, 0, 255),
                'attempted_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Count consecutive failed login attempts for `$username` within `$windowSeconds`
     * since the user's most recent successful login.
     */
    public function countRecentFailuresForUsername(string $username, int $windowSeconds): int
    {
        $normalized = strtolower(trim($username));
        $cutoff = date('Y-m-d H:i:s', time() - max(1, $windowSeconds));

        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS failure_count
             FROM login_attempts
             WHERE username = :username
               AND was_successful = 0
               AND attempted_at >= :cutoff
               AND id > COALESCE(
                   (SELECT MAX(id) FROM login_attempts WHERE username = :username_sub AND was_successful = 1),
                   0
               )',
            [
                'username' => $normalized,
                'cutoff' => $cutoff,
                'username_sub' => $normalized,
            ]
        );

        return (int) ($row['failure_count'] ?? 0);
    }

    /**
     * Count failed login attempts from `$ipAddress` within `$windowSeconds`.
     */
    public function countRecentFailuresForIp(string $ipAddress, int $windowSeconds): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(1, $windowSeconds));

        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS failure_count
             FROM login_attempts
             WHERE ip_address = :ip_address
               AND was_successful = 0
               AND attempted_at >= :cutoff',
            [
                'ip_address' => $ipAddress,
                'cutoff' => $cutoff,
            ]
        );

        return (int) ($row['failure_count'] ?? 0);
    }

    /**
     * Calculate remaining lockout seconds based on the most recent blocking attempt in window.
     */
    public function remainingLockoutSeconds(string $username, string $ipAddress, int $windowSeconds): int
    {
        $window = max(1, $windowSeconds);
        $cutoff = date('Y-m-d H:i:s', time() - $window);

        $row = $this->connection->fetchOne(
            'SELECT MAX(attempted_at) AS latest_attempt
             FROM login_attempts
             WHERE (username = :username OR ip_address = :ip_address)
               AND was_successful = 0
               AND attempted_at >= :cutoff',
            [
                'username' => strtolower(trim($username)),
                'ip_address' => $ipAddress,
                'cutoff' => $cutoff,
            ]
        );

        $latestAttempt = $row['latest_attempt'] ?? null;
        if (!is_string($latestAttempt) || $latestAttempt === '') {
            return $window;
        }

        $latestTimestamp = strtotime($latestAttempt);
        if ($latestTimestamp === false) {
            return $window;
        }

        $elapsed = max(0, time() - $latestTimestamp);

        return max(1, $window - $elapsed);
    }
}
