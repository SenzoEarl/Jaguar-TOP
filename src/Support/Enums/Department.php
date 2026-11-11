<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Canonical staff department slugs.
 *
 * Each slug maps 1:1 to:
 *  - `departments.slug` in MariaDB
 *  - `storage/departments/{slug}/` non-web file storage
 *  - `/staff/{slug}/dashboard` staff workspace route
 *
 * // [FUTURE:HR-MODULE] Add `case HumanResources = 'hr';` here and register its
 * // permissions, navigation item, and storage folder without touching core auth.
 */
enum Department: string
{
    case GeneralAdmin = 'general-admin';
    case Sales = 'sales';
    case Care = 'care';
    case Supervisor = 'supervisor';
    case Manager = 'manager';
    case SeniorManager = 'senior-manager';
    case Director = 'director';
    case Developer = 'developer';

    /**
     * Human-readable department title.
     */
    public function label(): string
    {
        return match ($this) {
            self::GeneralAdmin => 'General Admin',
            self::Sales => 'Sales',
            self::Care => 'Care',
            self::Supervisor => 'Supervisor',
            self::Manager => 'Manager',
            self::SeniorManager => 'Senior Manager',
            self::Director => 'Director',
            self::Developer => 'Developer / Root',
        };
    }

    /**
     * Owning Role level for this department workspace.
     */
    public function role(): Role
    {
        return match ($this) {
            self::GeneralAdmin, self::Sales, self::Care => Role::Admin,
            self::Supervisor => Role::Supervisor,
            self::Manager => Role::Manager,
            self::SeniorManager => Role::SeniorManager,
            self::Director => Role::Director,
            self::Developer => Role::Developer,
        };
    }

    /**
     * Whether this department belongs to the Level-1 collaborative Admin group.
     */
    public function isLevelOne(): bool
    {
        return $this->role() === Role::Admin;
    }

    /**
     * Default dashboard route path for this department.
     */
    public function dashboardPath(): string
    {
        return '/staff/' . $this->value . '/dashboard';
    }

    /**
     * All canonical department slug strings.
     *
     * @return list<string>
     */
    public static function allSlugs(): array
    {
        return array_map(
            static fn (self $dept): string => $dept->value,
            self::cases()
        );
    }
}
