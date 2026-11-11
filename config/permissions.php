<?php

/**
 * Explicit RBAC Permission Map (Single Source of Truth).
 *
 * Design principles:
 *  1. Deny by default: any role, level, or capability omitted here is denied.
 *  2. No implicit numeric inheritance (`if ($level > ...)` is forbidden):
 *     each role explicitly lists which levels it can view and mutate.
 *  3. Level-1 Collaborative Cross-Department Read Rule:
 *     Admin users (Level 1) in `general-admin`, `sales`, and `care` collaborate
 *     tightly, so they explicitly receive READ access to one another's Level-1
 *     dashboards, task views, and department file listings. WRITE access (creating
 *     tasks, changing statuses, uploading department files) stays strictly scoped
 *     to the user's own assigned department.
 *
 * // [FUTURE:HR-MODULE] Add the new department slug under `departments` and include
 * // its owning role level in `roles.*.visible_departments` / `writable_departments`.
 */

declare(strict_types=1);

use App\Support\Enums\Department;
use App\Support\Enums\Role;

return [
    'level1_cross_department_read' => [
        'enabled' => true,
        'description' => 'Level-1 Admin departments share read-only visibility across '
            . 'general-admin, sales, and care while keeping writes isolated to their own department.',
        'departments' => [
            Department::GeneralAdmin->value,
            Department::Sales->value,
            Department::Care->value,
        ],
    ],

    'roles' => [
        Role::Customer->value => [
            'can_access_staff_portal' => false,
            'visible_levels' => [Role::Customer->value],
            'writable_levels' => [Role::Customer->value],
            'visible_departments' => [],
            'writable_departments' => [],
        ],
        Role::Admin->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [Role::Admin->value],
            'writable_levels' => [Role::Admin->value],
            // Explicit L1 cross-department read visibility; own department is resolved at runtime for writes.
            'visible_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
            ],
            // Empty list means "own assigned department only" (enforced by AccessControl).
            'writable_departments' => [],
        ],
        Role::Supervisor->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [Role::Admin->value, Role::Supervisor->value],
            'writable_levels' => [Role::Admin->value, Role::Supervisor->value],
            'visible_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
            ],
            'writable_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
            ],
        ],
        Role::Manager->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
            ],
            'writable_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
            ],
            'visible_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
            ],
            'writable_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
            ],
        ],
        Role::SeniorManager->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
            ],
            'writable_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
            ],
            'visible_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
                Department::SeniorManager->value,
            ],
            'writable_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
                Department::SeniorManager->value,
            ],
        ],
        Role::Director->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
                Role::Director->value,
            ],
            'writable_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
                Role::Director->value,
            ],
            'visible_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
                Department::SeniorManager->value,
                Department::Director->value,
            ],
            'writable_departments' => [
                Department::GeneralAdmin->value,
                Department::Sales->value,
                Department::Care->value,
                Department::Supervisor->value,
                Department::Manager->value,
                Department::SeniorManager->value,
                Department::Director->value,
            ],
        ],
        Role::Developer->value => [
            'can_access_staff_portal' => true,
            'visible_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
                Role::Director->value,
                Role::Developer->value,
            ],
            'writable_levels' => [
                Role::Admin->value,
                Role::Supervisor->value,
                Role::Manager->value,
                Role::SeniorManager->value,
                Role::Director->value,
                Role::Developer->value,
            ],
            'visible_departments' => Department::allSlugs(),
            'writable_departments' => Department::allSlugs(),
        ],
    ],

    'capabilities' => [
        'booking.create' => [
            Role::Customer->value,
            Role::Admin->value,
            Role::Supervisor->value,
            Role::Manager->value,
            Role::SeniorManager->value,
            Role::Director->value,
            Role::Developer->value,
        ],
        'booking.view_own' => [
            Role::Customer->value,
            Role::Admin->value,
            Role::Supervisor->value,
            Role::Manager->value,
            Role::SeniorManager->value,
            Role::Director->value,
            Role::Developer->value,
        ],
        'staff.portal.view' => [
            Role::Admin->value,
            Role::Supervisor->value,
            Role::Manager->value,
            Role::SeniorManager->value,
            Role::Director->value,
            Role::Developer->value,
        ],
        'tasks.manage' => [
            Role::Admin->value,
            Role::Supervisor->value,
            Role::Manager->value,
            Role::SeniorManager->value,
            Role::Director->value,
            Role::Developer->value,
        ],
        'files.manage' => [
            Role::Admin->value,
            Role::Supervisor->value,
            Role::Manager->value,
            Role::SeniorManager->value,
            Role::Director->value,
            Role::Developer->value,
        ],
        'system.diagnostics.view' => [
            Role::Developer->value,
        ],
    ],
];
