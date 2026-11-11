<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireCsrfMiddleware;
use App\Modules\Booking\Controllers\BookingController;
use App\Modules\Booking\Controllers\CustomerDashboardController;

return static function (Router $router): void {
    $router->get('/bookings', [BookingController::class, 'createForm']);
    $router->get('/bookings/availability', [BookingController::class, 'checkAvailability']);
    $router->post(
        '/bookings/availability',
        [BookingController::class, 'checkAvailability'],
        [RequireCsrfMiddleware::class]
    );

    $router->post('/bookings', [BookingController::class, 'store'], [
        RequireAuthMiddleware::class,
        RequireCsrfMiddleware::class,
    ]);

    $router->get('/bookings/{id:\d+}/confirmation', [BookingController::class, 'confirmation'], [
        RequireAuthMiddleware::class,
    ]);

    $router->get('/account/dashboard', [CustomerDashboardController::class, 'index'], [
        RequireAuthMiddleware::class,
    ]);
};
