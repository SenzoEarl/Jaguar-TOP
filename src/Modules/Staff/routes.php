<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireDepartmentMiddleware;
use App\Middleware\RequireMinLevelMiddleware;
use App\Modules\Staff\Controllers\StaffDashboardController;
use App\Support\Enums\Role;

return static function (Router $router): void {
    $router->get('/staff/dashboard', [StaffDashboardController::class, 'redirectToDefault'], [
        RequireAuthMiddleware::class,
        [RequireMinLevelMiddleware::class, ['min_level' => Role::Admin]],
    ]);

    $router->get('/staff/admin/dashboard', [StaffDashboardController::class, 'redirectAdmin'], [
        RequireAuthMiddleware::class,
        [RequireMinLevelMiddleware::class, ['min_level' => Role::Admin]],
    ]);

    $router->get('/staff/{section}/dashboard', [StaffDashboardController::class, 'showDepartmentDashboard'], [
        RequireAuthMiddleware::class,
        [RequireDepartmentMiddleware::class, ['mode' => 'read']],
    ]);
};
