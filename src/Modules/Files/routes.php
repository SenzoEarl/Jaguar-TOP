<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireCsrfMiddleware;
use App\Middleware\RequireDepartmentMiddleware;
use App\Middleware\RequireMinLevelMiddleware;
use App\Modules\Files\Controllers\FileController;
use App\Support\Enums\Role;

return static function (Router $router): void {
    $router->get('/staff/files', [FileController::class, 'index'], [
        RequireAuthMiddleware::class,
        [RequireMinLevelMiddleware::class, ['min_level' => Role::Admin]],
    ]);

    $router->get('/staff/files/download/{id:\d+}', [FileController::class, 'download'], [
        RequireAuthMiddleware::class,
        [RequireMinLevelMiddleware::class, ['min_level' => Role::Admin]],
    ]);

    $router->get('/staff/files/{department}', [FileController::class, 'departmentIndex'], [
        RequireAuthMiddleware::class,
        [RequireDepartmentMiddleware::class, ['mode' => 'read']],
    ]);

    $router->post('/staff/files/{department}/upload', [FileController::class, 'upload'], [
        RequireAuthMiddleware::class,
        RequireCsrfMiddleware::class,
        [RequireDepartmentMiddleware::class, ['mode' => 'write']],
    ]);
};
