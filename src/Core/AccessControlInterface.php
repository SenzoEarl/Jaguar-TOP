<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Enums\Department;
use App\Support\Enums\Role;

/**
 * Authorization contract enforced across route middleware, controllers, and views.
 *
 * Implemented centrally by App\Modules\Auth\Services\AccessControl.
 */
interface AccessControlInterface
{
    public function canAccessStaffPortal(?AuthenticatedUserInterface $user): bool;

    public function hasRole(?AuthenticatedUserInterface $user, Role $role): bool;

    public function hasMinLevel(?AuthenticatedUserInterface $user, Role $minimumRole): bool;

    public function can(?AuthenticatedUserInterface $user, string $capability): bool;

    public function canViewDepartment(?AuthenticatedUserInterface $user, Department $department): bool;

    public function canWriteDepartment(?AuthenticatedUserInterface $user, Department $department): bool;

    public function isReadOnlyDepartmentView(?AuthenticatedUserInterface $user, Department $department): bool;

    public function defaultLandingPath(AuthenticatedUserInterface $user): string;
}
