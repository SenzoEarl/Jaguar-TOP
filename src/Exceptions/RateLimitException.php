<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when brute-force or rate-limiting thresholds are exceeded (HTTP 429).
 *
 * // [SECURITY:BRUTE-FORCE] Triggers Retry-After header and safe 429 page.
 */
class RateLimitException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly int $retryAfterSeconds,
        string $publicMessage = 'Too many attempts detected. Please wait before trying again.',
        string $internalMessage = 'Authentication rate limit exceeded.',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            429,
            $publicMessage,
            $internalMessage,
            'RATE_LIMIT_EXCEEDED',
            $context,
            $previous
        );
    }

    /**
     * Seconds the client should wait before retrying.
     */
    public function getRetryAfterSeconds(): int
    {
        return max(1, $this->retryAfterSeconds);
    }
}
