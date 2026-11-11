<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\AccessControl;
use App\Support\Enums\Department;
use App\Support\Enums\Role;
use PHPUnit\Framework\TestCase;

/**
 * Verifies centralized RBAC rules, deny-by-default semantics, Level-1 cross-department
 * read-only visibility, and strict write isolation.
 */
final class AccessControlTest extends TestCase
{
    private AccessControl $accessControl;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $config = Config::loadFromRoot($root);
        $this->accessControl = new AccessControl($config);
    }

    public function testUnauthenticatedAndInactiveUsersAreDeniedEverywhere(): void
    {
        self::assertFalse($this->accessControl->canAccessStaffPortal(null));
        self::assertFalse($this->accessControl->canViewDepartment(null, Department::GeneralAdmin));
        self::assertFalse($this->accessControl->canWriteDepartment(null, Department::GeneralAdmin));
        self::assertFalse($this->accessControl->can(null, 'staff.portal.access'));

        $inactiveDirector = $this->makeUser(50, Role::Director, Department::Director, false);
        self::assertFalse($this->accessControl->canAccessStaffPortal($inactiveDirector));
        self::assertFalse($this->accessControl->canViewDepartment($inactiveDirector, Department::Director));
        self::assertFalse($this->accessControl->canWriteDepartment($inactiveDirector, Department::Director));
    }

    public function testCustomerHasZeroStaffAccess(): void
    {
        $customer = $this->makeUser(1, Role::Customer, null);

        self::assertFalse($this->accessControl->canAccessStaffPortal($customer));
        self::assertTrue($this->accessControl->hasRole($customer, Role::Customer));
        self::assertTrue($this->accessControl->can($customer, 'booking.view_own'));
        self::assertTrue($this->accessControl->can($customer, 'booking.create'));
        self::assertFalse($this->accessControl->can($customer, 'staff.portal.view'));
        self::assertFalse($this->accessControl->canViewDepartment($customer, Department::Sales));
        self::assertSame('/account/dashboard', $this->accessControl->defaultLandingPath($customer));
    }

    public function testLevelOneCrossDepartmentReadOnlyAndWriteIsolation(): void
    {
        $generalAdmin = $this->makeUser(2, Role::Admin, Department::GeneralAdmin);
        $salesAdmin = $this->makeUser(3, Role::Admin, Department::Sales);
        $careAdmin = $this->makeUser(4, Role::Admin, Department::Care);

        // Own department has both read and write access.
        self::assertTrue($this->accessControl->canViewDepartment($generalAdmin, Department::GeneralAdmin));
        self::assertTrue($this->accessControl->canWriteDepartment($generalAdmin, Department::GeneralAdmin));
        self::assertFalse($this->accessControl->isReadOnlyDepartmentView($generalAdmin, Department::GeneralAdmin));

        // Cross-department L1 read-only works across all three L1 departments.
        self::assertTrue($this->accessControl->canViewDepartment($generalAdmin, Department::Sales));
        self::assertFalse($this->accessControl->canWriteDepartment($generalAdmin, Department::Sales));
        self::assertTrue($this->accessControl->isReadOnlyDepartmentView($generalAdmin, Department::Sales));

        self::assertTrue($this->accessControl->canViewDepartment($salesAdmin, Department::Care));
        self::assertFalse($this->accessControl->canWriteDepartment($salesAdmin, Department::Care));
        self::assertTrue($this->accessControl->isReadOnlyDepartmentView($salesAdmin, Department::Care));

        self::assertTrue($this->accessControl->canViewDepartment($careAdmin, Department::GeneralAdmin));
        self::assertFalse($this->accessControl->canWriteDepartment($careAdmin, Department::GeneralAdmin));

        // Higher-level departments remain strictly invisible to Level-1 users.
        self::assertFalse($this->accessControl->canViewDepartment($salesAdmin, Department::Supervisor));
        self::assertFalse($this->accessControl->canViewDepartment($salesAdmin, Department::Developer));
    }

    public function testHierarchyRespectsExplicitPermissionMapLevels(): void
    {
        $supervisor = $this->makeUser(5, Role::Supervisor, Department::Supervisor);
        $director = $this->makeUser(8, Role::Director, Department::Director);
        $developer = $this->makeUser(9, Role::Developer, Department::Developer);

        self::assertTrue($this->accessControl->hasMinLevel($supervisor, Role::Admin));
        self::assertFalse($this->accessControl->hasMinLevel($supervisor, Role::Manager));
        self::assertFalse($this->accessControl->canViewDepartment($supervisor, Department::Manager));
        self::assertFalse($this->accessControl->canWriteDepartment($supervisor, Department::Manager));
        self::assertTrue($this->accessControl->canViewDepartment($supervisor, Department::Sales));
        self::assertTrue($this->accessControl->canWriteDepartment($supervisor, Department::Sales));

        self::assertTrue($this->accessControl->canViewDepartment($director, Department::Manager));
        self::assertFalse($this->accessControl->can($director, 'system.diagnostics.view'));

        self::assertTrue($this->accessControl->can($developer, 'system.diagnostics.view'));
        self::assertTrue($this->accessControl->canWriteDepartment($developer, Department::Developer));
    }

    private function makeUser(int $id, Role $role, ?Department $dept, bool $active = true): User
    {
        return new User(
            $id,
            'user' . $id,
            'user' . $id . '@user.com',
            'User ' . $id,
            '$argon2id$stub',
            $role,
            $dept,
            $dept !== null ? 1 : null,
            $active,
            null,
            '2026-11-11 00:00:00'
        );
    }
}
