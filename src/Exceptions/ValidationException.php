<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when input validation fails (HTTP 422 Unprocessable Entity).
 */
class ValidationException extends AppException
{
    /**
     * @param array<string, list<string>> $errors Field-keyed validation error messages
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly array $errors,
        string $publicMessage = 'Please review the highlighted form fields and try again.',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            422,
            $publicMessage,
            'Validation failed: ' . implode(', ', array_keys($errors)),
            'VALIDATION_ERROR',
            $context,
            $previous
        );
    }

    /**
     * Field-level validation errors for form re-population or JSON payloads.
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
