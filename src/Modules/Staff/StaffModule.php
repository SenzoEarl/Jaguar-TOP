<?php

declare(strict_types=1);

namespace App\Modules\Staff;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\ModuleRegistry;
use App\Core\Router;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Files\Repositories\FileRepository;
use App\Modules\Staff\Controllers\StaffDashboardController;
use App\Modules\Staff\Services\StaffDashboardService;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskService;
use App\Support\Navigation\NavigationRegistry;

/**
 * Staff Module — owns role/department dashboards and staff portal shell navigation.
 */
final class StaffModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Staff';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(
            StaffDashboardService::class,
            static fn (Container $c): StaffDashboardService => new StaffDashboardService(
                $c->get(AccessControlInterface::class),
                $c->get(TaskService::class),
                $c->get(TaskRepository::class),
                $c->get(FileRepository::class),
                $c->get(BookingRepository::class),
                $c->get(UserRepository::class),
                $c->get(ModuleRegistry::class),
                $c->get(Config::class)
            )
        );

        $container->singleton(
            StaffDashboardController::class,
            static fn (Container $c): StaffDashboardController => new StaffDashboardController(
                $c->get(ViewFactory::class),
                $c->get(StaffDashboardService::class),
                $c->get(AccessControlInterface::class),
                $c->get(NavigationRegistry::class),
                $c->get(Config::class)
            )
        );
    }

    public function registerRoutes(Router $router): void
    {
        $register = require __DIR__ . '/routes.php';
        $register($router);
    }
}
