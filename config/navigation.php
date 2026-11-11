<?php

/**
 * Staff Portal Navigation Registry Configuration.
 *
 * Adding a new department dashboard or staff module link requires ONLY adding an
 * entry here; NavigationRegistry automatically filters visibility via AccessControl.
 *
 * // [FUTURE:HR-MODULE] Register the HR dashboard item inside the appropriate group below.
 * // [FUTURE:MANAGER-REPORTING] Register executive/manager analytics pages here.
 */

declare(strict_types=1);

use App\Support\Enums\Department;

return [
    'groups' => [
        [
            'heading' => 'Level 1 — Admin Departments',
            'items' => [
                [
                    'key' => 'dept-general-admin',
                    'label' => 'General Admin',
                    'path' => '/staff/general-admin/dashboard',
                    'department' => Department::GeneralAdmin->value,
                    'badge_text' => 'L1',
                ],
                [
                    'key' => 'dept-sales',
                    'label' => 'Sales',
                    'path' => '/staff/sales/dashboard',
                    'department' => Department::Sales->value,
                    'badge_text' => 'L1',
                ],
                [
                    'key' => 'dept-care',
                    'label' => 'Care',
                    'path' => '/staff/care/dashboard',
                    'department' => Department::Care->value,
                    'badge_text' => 'L1',
                ],
            ],
        ],
        [
            'heading' => 'Operations & Leadership',
            'items' => [
                [
                    'key' => 'dept-supervisor',
                    'label' => 'Supervisor',
                    'path' => '/staff/supervisor/dashboard',
                    'department' => Department::Supervisor->value,
                    'badge_text' => 'L2',
                ],
                [
                    'key' => 'dept-manager',
                    'label' => 'Manager',
                    'path' => '/staff/manager/dashboard',
                    'department' => Department::Manager->value,
                    'badge_text' => 'L3',
                ],
                [
                    'key' => 'dept-senior-manager',
                    'label' => 'Senior Manager',
                    'path' => '/staff/senior-manager/dashboard',
                    'department' => Department::SeniorManager->value,
                    'badge_text' => 'L4',
                ],
                [
                    'key' => 'dept-director',
                    'label' => 'Director',
                    'path' => '/staff/director/dashboard',
                    'department' => Department::Director->value,
                    'badge_text' => 'L5',
                ],
            ],
        ],
        [
            'heading' => 'Platform & Tools',
            'items' => [
                [
                    'key' => 'dept-developer',
                    'label' => 'Developer / Root',
                    'path' => '/staff/developer/dashboard',
                    'department' => Department::Developer->value,
                    'badge_text' => 'L6',
                ],
                [
                    'key' => 'staff-files',
                    'label' => 'Department Files',
                    'path' => '/staff/files',
                    'capability' => 'files.manage',
                    'badge_text' => 'Vault',
                ],
            ],
        ],
    ],
];
