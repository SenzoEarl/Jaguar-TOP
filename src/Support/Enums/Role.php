<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Hierarchical Role Enum (levels 0..6).
 *
 * Stored as TINYINT in the `roles` and `users.role_level` columns.
 * Authorization decisions are NEVER made by ad-hoc numeric comparisons;
 * always delegate to AccessControl and config/permissions.php.
 */
enum Role: int
{
    case Customer = 0;
    case Admin = 1;
    case Supervisor = 2;
    case Manager = 3;
    case SeniorManager = 4;
    case Director = 5;
    case Developer = 6;

    /**
     * Canonical URL/config slug for this role.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Customer => 'customer',
            self::Admin => 'admin',
            self::Supervisor => 'supervisor',
            self::Manager => 'manager',
            self::SeniorManager => 'senior-manager',
            self::Director => 'director',
            self::Developer => 'developer',
        };
    }

    /**
     * Human-readable display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Admin => 'Admin',
            self::Supervisor => 'Supervisor',
            self::Manager => 'Manager',
            self::SeniorManager => 'Senior Manager',
            self::Director => 'Director',
            self::Developer => 'Developer / Root',
        };
    }

    /**
     * Whether this role grants access to the internal staff portal.
     */
    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * Resolve a Role enum from its URL/config slug, or null if unknown.
     */
    public static function fromSlug(string $slug): ?self
    {
        $normalized = strtolower(trim($slug));
        foreach (self::cases() as $case) {
            if ($case->slug() === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
