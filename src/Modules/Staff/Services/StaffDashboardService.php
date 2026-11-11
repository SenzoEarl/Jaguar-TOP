<?php

declare(strict_types=1);

namespace App\Modules\Staff\Services;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\ModuleRegistry;
use App\Exceptions\AuthorizationException;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Files\Repositories\FileRepository;
use App\Modules\Staff\Models\DashboardSummary;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskService;
use App\Support\Enums\Department;
use App\Support\Enums\Role;

/**
 * Builds distinct operational dashboards per staff role and Level-1 department.
 *
 * // [FUTURE:MANAGER-REPORTING] Replace placeholder occupancy/EBITDA metrics with
 * // live aggregated queries from dedicated reporting views.
 * // [FUTURE:API] Expose JSON dashboard summary feed for mobile/SPA staff apps.
 * // [FUTURE:QUEUE] Surface background worker queue depths for Developer dashboard.
 * // [FUTURE:I18N] Localize metric labels and subtitles via a translation service.
 * // [FUTURE:MULTI-TENANT] Scope dashboard metrics and tasks by tenant_id here.
 */
final class StaffDashboardService
{
    public function __construct(
        private readonly AccessControlInterface $accessControl,
        private readonly TaskService $taskService,
        private readonly TaskRepository $taskRepository,
        private readonly FileRepository $fileRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly UserRepository $userRepository,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly Config $config
    ) {
    }

    public function buildDashboard(
        AuthenticatedUserInterface $actor,
        Department $department,
        bool $onlyMyTasks = false
    ): DashboardSummary {
        if (!$this->accessControl->canViewDepartment($actor, $department)) {
            throw new AuthorizationException('Access to this staff department dashboard is denied.');
        }

        $isReadOnly = $this->accessControl->isReadOnlyDepartmentView($actor, $department);
        $tasks = $this->taskService->listDepartmentTasks(
            $actor,
            $department,
            $onlyMyTasks ? $actor->getId() : null
        );
        $files = $this->fileRepository->findByDepartment($department);
        $staffMembers = $isReadOnly ? [] : $this->userRepository->listActiveStaff();
        $recentBookings = $this->bookingRepository->findRecent(5);

        [$headline, $subtitle, $metrics, $diagnostics] = $this->buildDepartmentMetrics($actor, $department);

        return new DashboardSummary(
            $department,
            $isReadOnly,
            $headline,
            $subtitle,
            $metrics,
            $tasks,
            $files,
            $staffMembers,
            $recentBookings,
            $diagnostics
        );
    }

    /**
     * @return array{
     *     0: string,
     *     1: string,
     *     2: list<array{title: string, value: string, detail: string, is_placeholder: bool}>,
     *     3: array<string, mixed>
     * }
     */
    private function buildDepartmentMetrics(AuthenticatedUserInterface $actor, Department $department): array
    {
        $openTasks = $this->taskRepository->countOpenByDepartment($department);
        $deptFiles = $this->fileRepository->countByDepartment($department);
        $activeBookings = $this->bookingRepository->countActiveBookings();
        $currency = $this->config->string('app.booking_currency', 'USD');
        $confirmedRevenue = format_money($this->bookingRepository->sumConfirmedRevenue(), $currency);

        $moduleNames = $this->moduleRegistry->registeredModuleNames();

        return match ($department) {
            Department::GeneralAdmin => [
                'General Admin Workspace',
                'Compliance registers, permit logs, and cross-property administrative readiness.',
                [
                    [
                        'title' => 'Open Admin Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Live tasks in General Admin queue',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Vault Documents',
                        'value' => (string) $deptFiles,
                        'detail' => 'Protected files in general-admin storage',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Permit Renewal Status',
                        'value' => '98% Compliant',
                        'detail' => 'Placeholder operational compliance feed',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Vendor Invoices Pending',
                        'value' => '4',
                        'detail' => 'Placeholder AP batch counter',
                        'is_placeholder' => true,
                    ],
                ],
                [],
            ],
            Department::Sales => [
                'Sales & Reservations Desk',
                'Direct booking velocity, group charters, and seasonal rate occupancy.',
                [
                    [
                        'title' => 'Active Bookings',
                        'value' => (string) $activeBookings,
                        'detail' => 'Confirmed & pending reservations',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Confirmed Value',
                        'value' => $confirmedRevenue,
                        'detail' => 'Live booking ledger sum',
                        'is_placeholder' => false,
                    ],
                    // // [FUTURE:SALES-MODULE] Live CRM opportunity pipeline value replaces placeholder.
                    [
                        'title' => 'Group Charter Pipeline',
                        'value' => '$18,400',
                        'detail' => 'Placeholder Q1 bespoke quotes',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Open Sales Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Follow-ups & rate updates',
                        'is_placeholder' => false,
                    ],
                ],
                [],
            ],
            Department::Care => [
                'Guest Care & Concierge',
                'Arrival readiness, dietary preferences, and field hospitality standards.',
                [
                    [
                        'title' => 'Open Care Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Guest prep & housekeeping actions',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Upcoming Arrivals',
                        'value' => (string) $activeBookings,
                        'detail' => 'Active stays requiring prep',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Guest Satisfaction Index',
                        'value' => '4.92 / 5.0',
                        'detail' => 'Placeholder post-stay survey score',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Unit Turnaround Readiness',
                        'value' => '100% Ready',
                        'detail' => 'Placeholder housekeeping status',
                        'is_placeholder' => true,
                    ],
                ],
                [],
            ],
            Department::Supervisor => [
                'Supervisor Operations Hub',
                'Shift handover coordination and Level-1 departmental oversight.',
                [
                    [
                        'title' => 'Supervisor Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Open supervisory assignments',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'L1 Cross-Team Active',
                        'value' => '3 Depts',
                        'detail' => 'General Admin, Sales & Care oversight',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Weekend Roster Coverage',
                        'value' => '14 / 14 Staff',
                        'detail' => 'Placeholder shift coverage metric',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Open Escalations',
                        'value' => '1 Low',
                        'detail' => 'Placeholder maintenance escalation count',
                        'is_placeholder' => true,
                    ],
                ],
                [],
            ],
            Department::Manager => [
                'Property Manager Dashboard',
                'Occupancy pacing, departmental throughput, and unit economics.',
                [
                    [
                        'title' => 'Confirmed Portfolio Revenue',
                        'value' => $confirmedRevenue,
                        'detail' => 'Live confirmed + completed stays',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Open Manager Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Operational planning items',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => '30-Day Occupancy Pace',
                        'value' => '76%',
                        'detail' => 'Placeholder forward occupancy forecast',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Vault Documents',
                        'value' => (string) $deptFiles,
                        'detail' => 'Protected manager reports',
                        'is_placeholder' => false,
                    ],
                ],
                [],
            ],
            Department::SeniorManager => [
                'Senior Manager Regional Console',
                'Multi-reserve SLA performance, infrastructure tenders, and regional compliance.',
                [
                    [
                        'title' => 'Senior Manager Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Escalated strategic actions',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Reserve SLA Score',
                        'value' => '99.1%',
                        'detail' => 'Placeholder regional service SLA',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Active Capex Tenders',
                        'value' => '2 Active',
                        'detail' => 'Placeholder vendor tender tracker',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Vault Documents',
                        'value' => (string) $deptFiles,
                        'detail' => 'Protected regional contracts',
                        'is_placeholder' => false,
                    ],
                ],
                [],
            ],
            Department::Director => [
                'Executive Director Brief',
                'Portfolio yield, conservation investments, and executive governance.',
                [
                    [
                        'title' => 'Portfolio Stay Revenue',
                        'value' => $confirmedRevenue,
                        'detail' => 'Live ledger bookings total',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Director Action Items',
                        'value' => (string) $openTasks,
                        'detail' => 'Governance sign-offs pending',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Conservation Fund Share',
                        'value' => '12.5%',
                        'detail' => 'Placeholder reserve reinvestment rate',
                        'is_placeholder' => true,
                    ],
                    [
                        'title' => 'Annual Yield Target',
                        'value' => '104% On Track',
                        'detail' => 'Placeholder executive forecast',
                        'is_placeholder' => true,
                    ],
                ],
                [],
            ],
            Department::Developer => [
                'Developer / Root System Console',
                'Application health, registered modules, RBAC inspection, and sanitized log tail.',
                [
                    [
                        'title' => 'PHP Runtime',
                        'value' => PHP_VERSION,
                        'detail' => 'Strict types & Argon2id enabled',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Loaded Modules',
                        'value' => (string) count($moduleNames),
                        'detail' => implode(', ', $moduleNames),
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'BladeOne Mode',
                        'value' => strtoupper($this->config->string('view.mode', 'auto')),
                        'detail' => 'Configured via .env VIEW_MODE',
                        'is_placeholder' => false,
                    ],
                    [
                        'title' => 'Developer Tasks',
                        'value' => (string) $openTasks,
                        'detail' => 'Platform maintenance backlog',
                        'is_placeholder' => false,
                    ],
                ],
                $this->accessControl->can($actor, 'system.diagnostics.view')
                    ? $this->collectDeveloperDiagnostics()
                    : [],
            ],
        };
    }

    /**
     * Collect sanitized non-secret system telemetry strictly for Developer/Root (Level 6).
     *
     * @return array{
     *     app_env: string,
     *     php_version: string,
     *     modules: list<string>,
     *     log_lines: list<string>
     * }
     */
    private function collectDeveloperDiagnostics(): array
    {
        $logBase = $this->config->resolvePath($this->config->string('security.logging.path', 'storage/logs/app.log'));
        $logDir = dirname($logBase);
        $candidates = glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [];
        rsort($candidates);

        $logLines = [];
        if ($candidates !== [] && is_readable($candidates[0])) {
            $lines = file($candidates[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $logLines = array_slice($lines, -12);
        }

        return [
            'app_env' => $this->config->string('app.env', 'local'),
            'php_version' => PHP_VERSION,
            'modules' => $this->moduleRegistry->registeredModuleNames(),
            'log_lines' => $logLines,
        ];
    }
}
