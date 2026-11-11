<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\Storage\DepartmentFileStorage;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Files\Controllers\FileController;
use App\Modules\Files\Repositories\FileRepository;
use App\Modules\Files\Services\FileService;
use App\Support\Navigation\NavigationRegistry;

/**
 * Files Module — owns isolated non-web department document management.
 */
final class FilesModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Files';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(FileRepository::class, static fn (Container $c): FileRepository => new FileRepository(
            $c->get(Connection::class)
        ));

        $container->singleton(FileService::class, static fn (Container $c): FileService => new FileService(
            $c->get(Connection::class),
            $c->get(FileRepository::class),
            $c->get(DepartmentFileStorage::class),
            $c->get(AccessControlInterface::class),
            $c->get(Validator::class),
            $c->get(AuditLogger::class)
        ));

        $container->singleton(FileController::class, static fn (Container $c): FileController => new FileController(
            $c->get(ViewFactory::class),
            $c->get(FileService::class),
            $c->get(AccessControlInterface::class),
            $c->get(NavigationRegistry::class),
            $c->get(Session::class),
            $c->get(Config::class)
        ));
    }

    public function registerRoutes(Router $router): void
    {
        $register = require __DIR__ . '/routes.php';
        $register($router);
    }
}
