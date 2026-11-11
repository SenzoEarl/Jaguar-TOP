<?php

declare(strict_types=1);

namespace App\Modules\Booking\Models;

use App\Support\Enums\BookingStatus;
use App\Support\Enums\ProductType;

/**
 * Domain entity representing a customer reservation record.
 */
final class Booking
{
    public function __construct(
        private readonly int $id,
        private readonly string $bookingReference,
        private readonly string $idempotencyKey,
        private readonly int $userId,
        private readonly int $productId,
        private readonly string $productName,
        private readonly string $productSlug,
        private readonly ProductType $productType,
        private readonly string $productLocation,
        private readonly string $checkInDate,
        private readonly string $checkOutDate,
        private readonly int $guestsCount,
        private readonly int $nightsCount,
        private readonly float $totalPrice,
        private readonly BookingStatus $status,
        private readonly ?string $specialRequests,
        private readonly string $createdAt
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['booking_reference'],
            (string) $row['idempotency_key'],
            (int) $row['user_id'],
            (int) $row['product_id'],
            (string) ($row['product_name'] ?? ''),
            (string) ($row['product_slug'] ?? ''),
            ProductType::from((string) ($row['product_type'] ?? 'lodge')),
            (string) ($row['product_location'] ?? ''),
            (string) $row['check_in_date'],
            (string) $row['check_out_date'],
            (int) $row['guests_count'],
            (int) $row['nights_count'],
            (float) $row['total_price'],
            BookingStatus::from((string) $row['status']),
            isset($row['special_requests']) && $row['special_requests'] !== ''
                ? (string) $row['special_requests']
                : null,
            (string) ($row['created_at'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBookingReference(): string
    {
        return $this->bookingReference;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getProductSlug(): string
    {
        return $this->productSlug;
    }

    public function getProductType(): ProductType
    {
        return $this->productType;
    }

    public function getProductLocation(): string
    {
        return $this->productLocation;
    }

    public function getCheckInDate(): string
    {
        return $this->checkInDate;
    }

    public function getCheckOutDate(): string
    {
        return $this->checkOutDate;
    }

    public function getGuestsCount(): int
    {
        return $this->guestsCount;
    }

    public function getNightsCount(): int
    {
        return $this->nightsCount;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function getSpecialRequests(): ?string
    {
        return $this->specialRequests;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function productDetailPath(): string
    {
        return '/' . $this->productType->urlSegment() . '/' . $this->productSlug;
    }
}
