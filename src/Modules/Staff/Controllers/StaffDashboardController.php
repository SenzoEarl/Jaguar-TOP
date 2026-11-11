<?php

declare(strict_types=1);

namespace App\Modules\Staff\Controllers;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Staff\Services\StaffDashboardService;
use App\Support\Enums\Department;
use App\Support\Enums\Role;
use App\Support\Navigation\NavigationRegistry;

/**
 * Renders distinct, role- and department-aware dashboards under `/staff/...`.
 */
final class StaffDashboardController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly StaffDashboardService $dashboardService,
        private readonly AccessControlInterface $accessControl,
        private readonly NavigationRegistry $navigation,
        private readonly Config $config
    ) {
    }

    public function redirectToDefault(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Please sign in to access the staff portal.');
        }

        $landing = $this->accessControl->defaultLandingPath($user);
        return Response::redirect(app_url($landing, $this->config->string('app.base_path', '')));
    }

    public function redirectAdmin(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Please sign in to access the staff portal.');
        }

        $dept = $user->getDepartment();
        $target = ($dept !== null && $dept->isLevelOne())
            ? $dept->dashboardPath()
            : Department::GeneralAdmin->dashboardPath();

        return Response::redirect(app_url($target, $this->config->string('app.base_path', '')));
    }

    /**
     * @param array<string, string> $params
     */
    public function showDepartmentDashboard(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $department = Department::tryFrom((string) ($params['section'] ?? ''));
        if ($department === null) {
            throw new NotFoundException('Staff dashboard section not found.');
        }

        $onlyMine = $request->query('filter') === 'mine';
        $summary = $this->dashboardService->buildDashboard($user, $department, $onlyMine);

        $html = $this->views->render('staff.' . $department->value . '.dashboard', [
            'pageTitle' => $summary->headline . ' | Staff Portal',
            'summary' => $summary,
            'onlyMine' => $onlyMine,
            'sidebarGroups' => $this->navigation->forUser($user, $request->path()),
            'maxUploadBytes' => $this->config->int('uploads.max_bytes', 5242880),
            'allowedExtensions' => $this->config->array('uploads.allowed_extensions', []),
        ]);

        return Response::html($html);
    }
}
