<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when authentication is required or credentials are invalid (HTTP 401).
 */
class AuthenticationException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'Authentication is required to access this resource.',
        string $internalMessage = '',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            401,
            $publicMessage,
            $internalMessage,
            'AUTHENTICATION_ERROR',
            $context,
            $previous
        );
    }
}
