<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Wraps low-level PDO failures so SQL statements, schema details, and hostnames
 * are logged internally without ever reaching end users.
 *
 * // [SECURITY:ERRORS] Prevents raw PDOException messages from leaking.
 */
class DatabaseException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $internalMessage = 'Database query execution failed.',
        string $publicMessage = 'A service error occurred while processing your request. Please try again later.',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            500,
            $publicMessage,
            $internalMessage,
            'DATABASE_ERROR',
            $context,
            $previous
        );
    }
}
