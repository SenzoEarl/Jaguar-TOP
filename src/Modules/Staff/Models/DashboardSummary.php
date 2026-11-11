<?php

declare(strict_types=1);

namespace App\Modules\Staff\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Files\Models\DepartmentFile;
use App\Modules\Tasks\Models\Task;
use App\Support\Enums\Department;

/**
 * Typed ViewModel DTO aggregating role-aware staff dashboard widgets and data.
 */
final class DashboardSummary
{
    /**
     * @param list<array{title: string, value: string, detail: string, is_placeholder: bool}> $metrics
     * @param list<Task> $tasks
     * @param list<DepartmentFile> $files
     * @param list<User> $staffMembers
     * @param list<Booking> $recentBookings
     * @param array<string, mixed> $systemDiagnostics
     */
    public function __construct(
        public readonly Department $department,
        public readonly bool $isReadOnly,
        public readonly string $headline,
        public readonly string $subtitle,
        public readonly array $metrics,
        public readonly array $tasks,
        public readonly array $files,
        public readonly array $staffMembers,
        public readonly array $recentBookings,
        public readonly array $systemDiagnostics = []
    ) {
    }
}
