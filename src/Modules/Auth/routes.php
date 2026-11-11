<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireCsrfMiddleware;
use App\Middleware\RequireGuestMiddleware;
use App\Modules\Auth\Controllers\AuthController;

return static function (Router $router): void {
    $router->group('/account', [], static function (Router $group): void {
        $group->get('/login', [AuthController::class, 'showLogin'], [RequireGuestMiddleware::class]);
        $group->post(
            '/login',
            [AuthController::class, 'login'],
            [RequireGuestMiddleware::class, RequireCsrfMiddleware::class]
        );

        $group->get('/register', [AuthController::class, 'showRegister'], [RequireGuestMiddleware::class]);
        $group->post(
            '/register',
            [AuthController::class, 'register'],
            [RequireGuestMiddleware::class, RequireCsrfMiddleware::class]
        );

        $group->post(
            '/logout',
            [AuthController::class, 'logout'],
            [RequireAuthMiddleware::class, RequireCsrfMiddleware::class]
        );
    });
};
