<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Modules\Booking\Services\BookingService;
use App\Support\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

/**
 * Verifies pure booking date-interval overlap semantics, night calculations,
 * and status inventory holds.
 */
final class BookingOverlapTest extends TestCase
{
    public function testOverlappingAndAdjacentIntervalsFollowHalfOpenLogic(): void
    {
        // Existing stay: Dec 10 to Dec 15 [10, 15).
        self::assertTrue(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-10', '2026-12-15'));
        self::assertTrue(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-12', '2026-12-14'));
        self::assertTrue(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-08', '2026-12-18'));
        self::assertTrue(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-08', '2026-12-11'));
        self::assertTrue(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-14', '2026-12-18'));

        // Same-day check-out === next check-in is valid turnaround (no overlap).
        self::assertFalse(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-15', '2026-12-18'));
        self::assertFalse(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-06', '2026-12-10'));
        self::assertFalse(BookingService::datesOverlap('2026-12-10', '2026-12-15', '2026-12-20', '2026-12-23'));
    }

    public function testComputeNightsRejectsZeroOrNegativeDuration(): void
    {
        self::assertSame(4, BookingService::computeNights('2026-12-10', '2026-12-14'));

        $this->expectException(ValidationException::class);
        BookingService::computeNights('2026-12-14', '2026-12-14');
    }

    public function testOnlyActiveStatusesBlockDates(): void
    {
        self::assertTrue(BookingStatus::Pending->blocksDates());
        self::assertTrue(BookingStatus::Confirmed->blocksDates());
        self::assertFalse(BookingStatus::Cancelled->blocksDates());
        self::assertFalse(BookingStatus::Completed->blocksDates());
    }
}
