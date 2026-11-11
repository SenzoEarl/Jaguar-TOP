<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when a requested route, product, booking, or file does not exist (HTTP 404).
 */
class NotFoundException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'The requested resource could not be found.',
        string $internalMessage = '',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            404,
            $publicMessage,
            $internalMessage,
            'NOT_FOUND',
            $context,
            $previous
        );
    }
}
