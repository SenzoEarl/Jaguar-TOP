<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when a reservation date range conflicts with an existing booking (HTTP 409).
 */
class BookingConflictException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $publicMessage = 'Selected dates are no longer available for this property.',
        string $internalMessage = 'Booking date overlap detected under row lock.',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            409,
            $publicMessage,
            $internalMessage,
            'BOOKING_CONFLICT',
            $context,
            $previous
        );
    }
}
