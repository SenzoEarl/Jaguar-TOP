<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Support\Enums\ProductType;

/**
 * Domain model representing a lodge, camp, or accommodation unit.
 */
final class Product
{
    /**
     * @param list<string> $amenities
     */
    public function __construct(
        private readonly int $id,
        private readonly string $slug,
        private readonly string $name,
        private readonly ProductType $type,
        private readonly string $location,
        private readonly string $shortSummary,
        private readonly string $description,
        private readonly float $pricePerNight,
        private readonly int $maxGuests,
        private readonly int $bedrooms,
        private readonly array $amenities,
        private readonly string $imagePath,
        private readonly string $imageAlt,
        private readonly bool $isFeatured,
        private readonly bool $isActive
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $decodedAmenities = [];
        if (isset($row['amenities_json']) && is_string($row['amenities_json'])) {
            $parsed = json_decode($row['amenities_json'], true);
            if (is_array($parsed)) {
                $decodedAmenities = array_values(array_filter(array_map('strval', $parsed)));
            }
        }

        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            ProductType::from((string) $row['type']),
            (string) $row['location'],
            (string) $row['short_summary'],
            (string) $row['description'],
            (float) $row['price_per_night'],
            (int) $row['max_guests'],
            (int) $row['bedrooms'],
            $decodedAmenities,
            (string) $row['image_path'],
            (string) $row['image_alt'],
            (bool) $row['is_featured'],
            (bool) $row['is_active']
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ProductType
    {
        return $this->type;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getShortSummary(): string
    {
        return $this->shortSummary;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPricePerNight(): float
    {
        return $this->pricePerNight;
    }

    public function getMaxGuests(): int
    {
        return $this->maxGuests;
    }

    public function getBedrooms(): int
    {
        return $this->bedrooms;
    }

    /**
     * @return list<string>
     */
    public function getAmenities(): array
    {
        return $this->amenities;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function getImageAlt(): string
    {
        return $this->imageAlt;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function detailPath(): string
    {
        return '/' . $this->type->urlSegment() . '/' . $this->slug;
    }

    /**
     * Build safe JSON-LD LodgingBusiness / Accommodation structured data for SEO.
     */
    public function toJsonLd(string $appUrl, string $basePath, string $currency, string $brandName): string
    {
        $canonicalUrl = rtrim($appUrl, '/') . app_url($this->detailPath(), '');
        $imageUrl = rtrim($appUrl, '/') . app_url($this->imagePath, '');

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => $this->type === ProductType::Lodge ? 'LodgingBusiness' : 'Accommodation',
            'name' => $this->name,
            'description' => $this->shortSummary,
            'url' => $canonicalUrl,
            'image' => $imageUrl,
            'occupancy' => [
                '@type' => 'QuantitativeValue',
                'maxValue' => $this->maxGuests,
            ],
            'numberOfBedrooms' => $this->bedrooms,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $this->location,
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => strtoupper($currency),
                'price' => number_format($this->pricePerNight, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $brandName,
                ],
            ],
        ];

        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR
        );
    }
}
