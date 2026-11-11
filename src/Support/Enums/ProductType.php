<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Catalog product categories exposed in the public storefront.
 */
enum ProductType: string
{
    case Lodge = 'lodge';
    case Camp = 'camp';
    case Accommodation = 'accommodation';

    /**
     * Singular display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lodge => 'Lodge',
            self::Camp => 'Camp',
            self::Accommodation => 'Accommodation',
        };
    }

    /**
     * Plural category label for listings and SEO headings.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Lodge => 'Lodges',
            self::Camp => 'Camps',
            self::Accommodation => 'Accommodation',
        };
    }

    /**
     * Public URL path segment (`/lodges`, `/camps`, `/accommodation`).
     */
    public function urlSegment(): string
    {
        return match ($this) {
            self::Lodge => 'lodges',
            self::Camp => 'camps',
            self::Accommodation => 'accommodation',
        };
    }

    /**
     * Resolve a ProductType from either its URL segment or singular DB value.
     */
    public static function fromSegmentOrValue(string $input): ?self
    {
        return match (strtolower(trim($input))) {
            'lodge', 'lodges' => self::Lodge,
            'camp', 'camps' => self::Camp,
            'accommodation', 'accommodations' => self::Accommodation,
            default => null,
        };
    }
}
