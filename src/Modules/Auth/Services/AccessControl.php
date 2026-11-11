<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Support\Enums\Department;
use App\Support\Enums\Role;

/**
 * Single centralized RBAC decision engine backed by `config/permissions.php`.
 *
 * Enforces deny-by-default rules, explicit non-hardcoded role visibility,
 * and Level-1 collaborative cross-department read-only access.
 */
final class AccessControl implements AccessControlInterface
{
    /** @var array<string, mixed> */
    private readonly array $permissions;

    /**
     * @param array<string, mixed>|null $overrideMap Optional map for isolated unit testing
     */
    public function __construct(
        Config $config,
        ?array $overrideMap = null
    ) {
        $this->permissions = $overrideMap ?? $config->array('permissions', []);
    }

    public function canAccessStaffPortal(?AuthenticatedUserInterface $user): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        $roleRule = $this->roleRule($user->getRole());
        return (bool) ($roleRule['can_access_staff_portal'] ?? false);
    }

    public function hasRole(?AuthenticatedUserInterface $user, Role $role): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        return $user->getRole() === $role;
    }

    /**
     * Check whether `$user`'s role includes `$minimumRole` in its explicit `visible_levels` map.
     *
     * Avoids ad-hoc integer comparisons (`$level >= ...`); relies strictly on `config/permissions.php`.
     */
    public function hasMinLevel(?AuthenticatedUserInterface $user, Role $minimumRole): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        $roleRule = $this->roleRule($user->getRole());
        $visibleLevels = $roleRule['visible_levels'] ?? [];

        return is_array($visibleLevels) && in_array($minimumRole->value, $visibleLevels, true);
    }

    public function can(?AuthenticatedUserInterface $user, string $capability): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        $capabilities = $this->permissions['capabilities'] ?? [];
        if (!is_array($capabilities) || !isset($capabilities[$capability]) || !is_array($capabilities[$capability])) {
            return false;
        }

        return in_array($user->getRole()->value, $capabilities[$capability], true);
    }

    /**
     * Determine whether `$user` has READ access to `$department`'s dashboard, tasks, and files.
     *
     * Explicit rule: Level-1 Admin users (General Admin, Sales, Care) can view each
     * other's Level-1 departments read-only (`visible_departments` in config/permissions.php).
     */
    public function canViewDepartment(?AuthenticatedUserInterface $user, Department $department): bool
    {
        if (!$this->canAccessStaffPortal($user) || $user === null) {
            return false;
        }

        $roleRule = $this->roleRule($user->getRole());
        $visibleDepartments = $roleRule['visible_departments'] ?? [];

        if (is_array($visibleDepartments) && in_array($department->value, $visibleDepartments, true)) {
            if ($user->getRole() === Role::Admin) {
                $crossReadEnabled = (bool) ($this->permissions['level1_cross_department_read']['enabled'] ?? false);
                if ($crossReadEnabled) {
                    return true;
                }
                return $user->getDepartment() === $department;
            }
            return true;
        }

        return $user->getDepartment() === $department;
    }

    /**
     * Determine whether `$user` has WRITE access (create tasks, change statuses, upload files)
     * inside `$department`.
     *
     * Level-1 Admin write access is strictly isolated to the user's own assigned department.
     */
    public function canWriteDepartment(?AuthenticatedUserInterface $user, Department $department): bool
    {
        if (!$this->canAccessStaffPortal($user) || $user === null) {
            return false;
        }

        if ($user->getRole() === Role::Admin) {
            return $user->getDepartment() === $department;
        }

        $roleRule = $this->roleRule($user->getRole());
        $writableDepartments = $roleRule['writable_departments'] ?? [];

        return is_array($writableDepartments) && in_array($department->value, $writableDepartments, true);
    }

    public function isReadOnlyDepartmentView(?AuthenticatedUserInterface $user, Department $department): bool
    {
        return $this->canViewDepartment($user, $department) && !$this->canWriteDepartment($user, $department);
    }

    public function defaultLandingPath(AuthenticatedUserInterface $user): string
    {
        if (!$this->canAccessStaffPortal($user)) {
            return '/account/dashboard';
        }

        $department = $user->getDepartment();
        if ($department !== null) {
            return $department->dashboardPath();
        }

        return match ($user->getRole()) {
            Role::Admin => Department::GeneralAdmin->dashboardPath(),
            Role::Supervisor => Department::Supervisor->dashboardPath(),
            Role::Manager => Department::Manager->dashboardPath(),
            Role::SeniorManager => Department::SeniorManager->dashboardPath(),
            Role::Director => Department::Director->dashboardPath(),
            Role::Developer => Department::Developer->dashboardPath(),
            Role::Customer => '/account/dashboard',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRule(Role $role): array
    {
        $roles = $this->permissions['roles'] ?? [];
        if (!is_array($roles)) {
            return [];
        }

        $rule = $roles[$role->value] ?? [];
        return is_array($rule) ? $rule : [];
    }
}
