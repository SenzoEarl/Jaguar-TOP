<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when an authenticated user lacks permission for a resource (HTTP 403).
 */
class AuthorizationException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'You do not have permission to access this resource.',
        string $internalMessage = '',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            403,
            $publicMessage,
            $internalMessage,
            'AUTHORIZATION_ERROR',
            $context,
            $previous
        );
    }
}
