<?php

declare(strict_types=1);

namespace App\Modules\Booking\Services;

use App\Core\Config;
use App\Core\Request;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BookingConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Support\Enums\BookingStatus;
use DateTimeImmutable;

/**
 * Reservation domain service implementing atomic overlap prevention and idempotency.
 */
final class BookingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly BookingRepository $bookings,
        private readonly ProductRepository $products,
        private readonly Validator $validator,
        private readonly AuditLogger $auditLogger,
        private readonly Config $config
    ) {
    }

    /**
     * Pure half-open interval overlap check: `[startA, endA)` vs `[startB, endB)`.
     *
     * Allows back-to-back turnaround on the same day (`check_out === next check_in`).
     */
    public static function datesOverlap(
        string $startA,
        string $endA,
        string $startB,
        string $endB
    ): bool {
        return $startA < $endB && $endA > $startB;
    }

    /**
     * Calculate exact night count between two validated ISO dates.
     */
    public static function computeNights(string $checkInDate, string $checkOutDate): int
    {
        $in = new DateTimeImmutable($checkInDate);
        $out = new DateTimeImmutable($checkOutDate);
        $days = (int) $in->diff($out)->days;

        if ($days <= 0 || $out <= $in) {
            throw new ValidationException([
                'check_out_date' => ['Check-out date must be at least one night after check-in date.'],
            ]);
        }

        return $days;
    }

    /**
     * Evaluate availability and server-computed pricing quote without reserving inventory.
     *
     * @param array<string, mixed> $input
     * @return array{
     *     available: bool,
     *     product_id: int,
     *     product_name: string,
     *     nights: int,
     *     price_per_night: float,
     *     total_price: float,
     *     currency: string,
     *     message: string
     * }
     */
    public function checkAvailability(array $input): array
    {
        $data = $this->validator->validate($input, [
            'product_id' => 'required|int|min:1',
            'check_in_date' => 'required|date|after_or_equal_today',
            'check_out_date' => 'required|date|after_field:check_in_date',
            'guests_count' => 'required|int|min:1',
        ]);

        $product = $this->products->findById((int) $data['product_id']);
        if ($product === null) {
            throw new NotFoundException('Selected lodge, camp, or accommodation does not exist.');
        }

        $guests = (int) $data['guests_count'];
        if ($guests > $product->getMaxGuests()) {
            throw new ValidationException([
                'guests_count' => [
                    sprintf('%s accommodates a maximum of %d guests.', $product->getName(), $product->getMaxGuests()),
                ],
            ]);
        }

        $nights = self::computeNights((string) $data['check_in_date'], (string) $data['check_out_date']);
        $totalPrice = round($nights * $product->getPricePerNight(), 2);
        $hasConflict = $this->bookings->hasOverlappingBooking(
            $product->getId(),
            (string) $data['check_in_date'],
            (string) $data['check_out_date']
        );

        $currency = $this->config->string('app.booking_currency', 'USD');

        return [
            'available' => !$hasConflict,
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
            'nights' => $nights,
            'price_per_night' => $product->getPricePerNight(),
            'total_price' => $totalPrice,
            'currency' => $currency,
            'message' => $hasConflict
                ? 'Those dates overlap an existing confirmed reservation. Please choose alternative dates.'
                : sprintf('Available for %d night(s). Total stay: %s.', $nights, format_money($totalPrice, $currency)),
        ];
    }

    /**
     * Create a booking atomically with idempotency guarantee and InnoDB row locking.
     *
     * @param array<string, mixed> $input
     */
    public function createBooking(int $userId, array $input, Request $request): Booking
    {
        $maxGuestsCap = max(1, $this->config->int('app.booking_max_guests_default', 12));

        $data = $this->validator->validate($input, [
            'product_id' => 'required|int|min:1',
            'check_in_date' => 'required|date|after_or_equal_today',
            'check_out_date' => 'required|date|after_field:check_in_date',
            'guests_count' => 'required|int|min:1|max:' . $maxGuestsCap,
            'idempotency_key' => 'required|string|min:16|max:64',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $idempotencyKey = (string) $data['idempotency_key'];
        if (preg_match('/^[A-Za-z0-9_-]{16,64}$/', $idempotencyKey) !== 1) {
            throw new ValidationException([
                'idempotency_key' => ['Invalid booking idempotency key format.'],
            ]);
        }

        // Fast-path idempotency check before entering lock.
        $existing = $this->bookings->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            if ($existing->getUserId() !== $userId) {
                throw new AuthorizationException('Idempotency key belongs to another customer.');
            }
            return $existing;
        }

        $productId = (int) $data['product_id'];
        $checkInDate = (string) $data['check_in_date'];
        $checkOutDate = (string) $data['check_out_date'];
        $guestsCount = (int) $data['guests_count'];
        $nightsCount = self::computeNights($checkInDate, $checkOutDate);
        $specialRequests = isset($data['special_requests']) && $data['special_requests'] !== ''
            ? (string) $data['special_requests']
            : null;

        /** @var Booking $booking */
        $booking = $this->connection->transaction(function () use (
            $userId,
            $productId,
            $checkInDate,
            $checkOutDate,
            $guestsCount,
            $nightsCount,
            $idempotencyKey,
            $specialRequests
        ): Booking {
            // 1. Acquire exclusive row lock on the target product to serialize concurrent requests.
            $lockedProduct = $this->bookings->lockActiveProduct($productId);
            if ($lockedProduct === null) {
                throw new NotFoundException('The selected stay is unavailable or does not exist.');
            }

            // 2. Re-check idempotency inside the lock in case a duplicate arrived concurrently.
            $existingInsideTx = $this->bookings->findByIdempotencyKey($idempotencyKey);
            if ($existingInsideTx !== null) {
                if ($existingInsideTx->getUserId() !== $userId) {
                    throw new AuthorizationException('Idempotency key belongs to another customer.');
                }
                return $existingInsideTx;
            }

            // 3. Enforce property capacity limits.
            if ($guestsCount > $lockedProduct['max_guests']) {
                throw new ValidationException([
                    'guests_count' => [
                        sprintf('Selected property allows up to %d guests.', $lockedProduct['max_guests']),
                    ],
                ]);
            }

            // 4. Lock and detect overlapping active reservations.
            $overlaps = $this->bookings->lockOverlappingBookingIds($productId, $checkInDate, $checkOutDate);
            if ($overlaps !== []) {
                throw new BookingConflictException(
                    'Selected dates conflict with an existing reservation. Please pick different dates.',
                    sprintf('Overlap on product #%d for %s..%s', $productId, $checkInDate, $checkOutDate),
                    ['product_id' => $productId, 'conflicting_ids' => $overlaps]
                );
            }

            // 5. Server-side price computation (never trust client price fields).
            $totalPrice = round($nightsCount * $lockedProduct['price_per_night'], 2);
            $reference = $this->generateBookingReference();

            // // [FUTURE:PAYMENTS] Initiate payment hold/capture here before confirming status.
            return $this->bookings->create(
                $reference,
                $idempotencyKey,
                $userId,
                $productId,
                $checkInDate,
                $checkOutDate,
                $guestsCount,
                $nightsCount,
                $totalPrice,
                BookingStatus::Confirmed,
                $specialRequests
            );
        });

        // // [FUTURE:NOTIFICATIONS] Enqueue reservation confirmation email/SMS job here.
        $this->auditLogger->record(
            'booking.created',
            $userId,
            'bookings',
            $booking->getId(),
            $request,
            [
                'reference' => $booking->getBookingReference(),
                'product_id' => $booking->getProductId(),
                'check_in' => $booking->getCheckInDate(),
                'check_out' => $booking->getCheckOutDate(),
                'total_price' => $booking->getTotalPrice(),
            ]
        );

        return $booking;
    }

    /**
     * @return list<Booking>
     */
    public function getUserHistory(int $userId): array
    {
        return $this->bookings->findForUser($userId);
    }

    public function getConfirmationForUser(int $bookingId, int $userId): Booking
    {
        $booking = $this->bookings->findByIdForUser($bookingId, $userId);
        if ($booking === null) {
            throw new NotFoundException('Reservation confirmation could not be found.');
        }

        return $booking;
    }

    private function generateBookingReference(): string
    {
        return sprintf('JGS-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(3))));
    }
}
