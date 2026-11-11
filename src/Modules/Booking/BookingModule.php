<?php

declare(strict_types=1);

namespace App\Modules\Booking;

use App\Core\Config;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Booking\Controllers\BookingController;
use App\Modules\Booking\Controllers\CustomerDashboardController;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\Services\CatalogService;

/**
 * Booking Module — owns availability checks, atomic idempotent reservations, and customer history.
 */
final class BookingModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Booking';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(
            BookingRepository::class,
            static fn (Container $c): BookingRepository => new BookingRepository(
                $c->get(Connection::class)
            )
        );

        $container->singleton(BookingService::class, static fn (Container $c): BookingService => new BookingService(
            $c->get(Connection::class),
            $c->get(BookingRepository::class),
            $c->get(ProductRepository::class),
            $c->get(Validator::class),
            $c->get(AuditLogger::class),
            $c->get(Config::class)
        ));

        $container->singleton(
            BookingController::class,
            static fn (Container $c): BookingController => new BookingController(
                $c->get(ViewFactory::class),
                $c->get(BookingService::class),
                $c->get(CatalogService::class),
                $c->get(Session::class),
                $c->get(Config::class)
            )
        );

        $container->singleton(
            CustomerDashboardController::class,
            static fn (Container $c): CustomerDashboardController => new CustomerDashboardController(
                $c->get(ViewFactory::class),
                $c->get(BookingService::class),
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
