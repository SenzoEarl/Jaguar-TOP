<?php

declare(strict_types=1);

namespace App\Modules\Booking\Repositories;

use App\Infrastructure\Database\Connection;
use App\Modules\Booking\Models\Booking;
use App\Support\Enums\BookingStatus;

/**
 * PDO repository for reservations, including row-level locking queries used
 * inside transactions to prevent concurrent double-bookings.
 */
final class BookingRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function findByIdempotencyKey(string $key): ?Booking
    {
        $row = $this->connection->fetchOne(
            'SELECT b.*, p.name AS product_name, p.slug AS product_slug,
                    p.type AS product_type, p.location AS product_location
             FROM bookings b
             INNER JOIN products p ON p.id = b.product_id
             WHERE b.idempotency_key = :idempotency_key
             LIMIT 1',
            ['idempotency_key' => $key]
        );

        return $row !== null ? Booking::fromRow($row) : null;
    }

    public function findByIdForUser(int $bookingId, int $userId): ?Booking
    {
        $row = $this->connection->fetchOne(
            'SELECT b.*, p.name AS product_name, p.slug AS product_slug,
                    p.type AS product_type, p.location AS product_location
             FROM bookings b
             INNER JOIN products p ON p.id = b.product_id
             WHERE b.id = :id AND b.user_id = :user_id
             LIMIT 1',
            [
                'id' => $bookingId,
                'user_id' => $userId,
            ]
        );

        return $row !== null ? Booking::fromRow($row) : null;
    }

    /**
     * @return list<Booking>
     */
    public function findForUser(int $userId): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT b.*, p.name AS product_name, p.slug AS product_slug,
                    p.type AS product_type, p.location AS product_location
             FROM bookings b
             INNER JOIN products p ON p.id = b.product_id
             WHERE b.user_id = :user_id
             ORDER BY b.created_at DESC, b.id DESC',
            ['user_id' => $userId]
        );

        return array_map(
            static fn (array $row): Booking => Booking::fromRow($row),
            $rows
        );
    }

    /**
     * Acquire an InnoDB row lock on the target product inside an active transaction.
     *
     * @return array{id: int, price_per_night: float, max_guests: int}|null
     */
    public function lockActiveProduct(int $productId): ?array
    {
        $lockClause = $this->connection->supportsRowLocking() ? ' FOR UPDATE' : '';
        $row = $this->connection->fetchOne(
            'SELECT id, price_per_night, max_guests
             FROM products
             WHERE id = :id AND is_active = 1' . $lockClause,
            ['id' => $productId]
        );

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'price_per_night' => (float) $row['price_per_night'],
            'max_guests' => (int) $row['max_guests'],
        ];
    }

    /**
     * Acquire row locks on any active bookings overlapping the requested date range.
     *
     * Must be executed inside the same InnoDB transaction as `lockActiveProduct()`.
     *
     * @return list<int>
     */
    public function lockOverlappingBookingIds(int $productId, string $checkInDate, string $checkOutDate): array
    {
        $lockClause = $this->connection->supportsRowLocking() ? ' FOR UPDATE' : '';
        $rows = $this->connection->fetchAll(
            'SELECT id
             FROM bookings
             WHERE product_id = :product_id
               AND status IN (:status_pending, :status_confirmed)
               AND check_in_date < :check_out
               AND check_out_date > :check_in' . $lockClause,
            [
                'product_id' => $productId,
                'status_pending' => BookingStatus::Pending->value,
                'status_confirmed' => BookingStatus::Confirmed->value,
                'check_out' => $checkOutDate,
                'check_in' => $checkInDate,
            ]
        );

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );
    }

    public function hasOverlappingBooking(int $productId, string $checkInDate, string $checkOutDate): bool
    {
        $row = $this->connection->fetchOne(
            'SELECT 1 AS conflict
             FROM bookings
             WHERE product_id = :product_id
               AND status IN (:status_pending, :status_confirmed)
               AND check_in_date < :check_out
               AND check_out_date > :check_in
             LIMIT 1',
            [
                'product_id' => $productId,
                'status_pending' => BookingStatus::Pending->value,
                'status_confirmed' => BookingStatus::Confirmed->value,
                'check_out' => $checkOutDate,
                'check_in' => $checkInDate,
            ]
        );

        return $row !== null;
    }

    public function create(
        string $bookingReference,
        string $idempotencyKey,
        int $userId,
        int $productId,
        string $checkInDate,
        string $checkOutDate,
        int $guestsCount,
        int $nightsCount,
        float $totalPrice,
        BookingStatus $status,
        ?string $specialRequests
    ): Booking {
        $this->connection->execute(
            'INSERT INTO bookings (
                booking_reference,
                idempotency_key,
                user_id,
                product_id,
                check_in_date,
                check_out_date,
                guests_count,
                nights_count,
                total_price,
                status,
                special_requests
            ) VALUES (
                :booking_reference,
                :idempotency_key,
                :user_id,
                :product_id,
                :check_in_date,
                :check_out_date,
                :guests_count,
                :nights_count,
                :total_price,
                :status,
                :special_requests
            )',
            [
                'booking_reference' => $bookingReference,
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'product_id' => $productId,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'guests_count' => $guestsCount,
                'nights_count' => $nightsCount,
                'total_price' => number_format($totalPrice, 2, '.', ''),
                'status' => $status->value,
                'special_requests' => $specialRequests,
            ]
        );

        $id = $this->connection->lastInsertId();
        $created = $this->findByIdForUser($id, $userId);
        if ($created === null) {
            throw new \RuntimeException('Created booking could not be reloaded.');
        }

        return $created;
    }

    public function countActiveBookings(): int
    {
        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS total FROM bookings WHERE status IN (:pending, :confirmed)',
            [
                'pending' => BookingStatus::Pending->value,
                'confirmed' => BookingStatus::Confirmed->value,
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function sumConfirmedRevenue(): float
    {
        $row = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(total_price), 0) AS total_revenue
             FROM bookings
             WHERE status IN (:confirmed, :completed)',
            [
                'confirmed' => BookingStatus::Confirmed->value,
                'completed' => BookingStatus::Completed->value,
            ]
        );

        return (float) ($row['total_revenue'] ?? 0);
    }

    /**
     * @return list<Booking>
     */
    public function findRecent(int $limit = 5): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT b.*, p.name AS product_name, p.slug AS product_slug,
                    p.type AS product_type, p.location AS product_location
             FROM bookings b
             INNER JOIN products p ON p.id = b.product_id
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT :limit',
            ['limit' => max(1, $limit)]
        );

        return array_map(
            static fn (array $row): Booking => Booking::fromRow($row),
            $rows
        );
    }
}
