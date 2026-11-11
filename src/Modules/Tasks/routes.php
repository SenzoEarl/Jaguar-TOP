<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireCsrfMiddleware;
use App\Middleware\RequireDepartmentMiddleware;
use App\Modules\Tasks\Controllers\TaskController;

return static function (Router $router): void {
    $router->post('/staff/{section}/tasks', [TaskController::class, 'store'], [
        RequireAuthMiddleware::class,
        RequireCsrfMiddleware::class,
        [RequireDepartmentMiddleware::class, ['mode' => 'write']],
    ]);

    $router->post('/staff/tasks/{id:\d+}/status', [TaskController::class, 'updateStatus'], [
        RequireAuthMiddleware::class,
        RequireCsrfMiddleware::class,
    ]);

    $router->patch('/staff/tasks/{id:\d+}/status', [TaskController::class, 'updateStatus'], [
        RequireAuthMiddleware::class,
        RequireCsrfMiddleware::class,
    ]);
};
