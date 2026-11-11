<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when department file upload validation or storage fails.
 */
class FileUploadException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'The uploaded file could not be accepted.',
        int $httpStatus = 422,
        string $internalMessage = '',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $httpStatus,
            $publicMessage,
            $internalMessage,
            'FILE_UPLOAD_ERROR',
            $context,
            $previous
        );
    }
}
