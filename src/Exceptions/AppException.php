<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base application exception carrying a safe client-facing message and HTTP status.
 *
 * // [SECURITY:ERRORS] Separates internal diagnostic details ($message, $context)
 * // logged to Monolog from the sanitized $publicMessage shown to users.
 */
class AppException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly int $httpStatus = 500,
        private readonly string $publicMessage = 'An unexpected error occurred.',
        string $internalMessage = '',
        private readonly string $errorCode = 'APP_ERROR',
        private readonly array $context = [],
        ?Throwable $previous = null
    ) {
        $resolvedInternal = $internalMessage !== '' ? $internalMessage : $publicMessage;
        parent::__construct($resolvedInternal, $httpStatus, $previous);
    }

    /**
     * HTTP status code to send to the client.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Safe message suitable for HTML error pages and JSON API responses.
     */
    public function getPublicMessage(): string
    {
        return $this->publicMessage;
    }

    /**
     * Stable machine-readable error identifier.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Structured diagnostic context recorded in logs only.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
