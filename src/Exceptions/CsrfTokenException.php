<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when a state-changing request fails CSRF token validation (HTTP 419).
 *
 * // [SECURITY:CSRF] Dedicated exception mapped to custom 419 page or JSON error.
 */
class CsrfTokenException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'Your security token has expired or is invalid. Please refresh the page and try again.',
        string $internalMessage = 'CSRF token mismatch or missing.',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            419,
            $publicMessage,
            $internalMessage,
            'CSRF_MISMATCH',
            $context,
            $previous
        );
    }
}
