<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Lifecycle status for customer reservations.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /**
     * Human-readable badge text.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    /**
     * Bootstrap 5 badge modifier class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning text-dark',
            self::Confirmed => 'bg-success',
            self::Cancelled => 'bg-secondary',
            self::Completed => 'bg-info text-dark',
        };
    }

    /**
     * Whether a booking in this state holds inventory and blocks overlapping dates.
     */
    public function blocksDates(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}
