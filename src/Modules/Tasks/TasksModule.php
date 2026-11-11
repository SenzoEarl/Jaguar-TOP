<?php

declare(strict_types=1);

namespace App\Modules\Tasks;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Tasks\Controllers\TaskController;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskService;

/**
 * Tasks Module — owns staff task lifecycle per department and assignee.
 */
final class TasksModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Tasks';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(TaskRepository::class, static fn (Container $c): TaskRepository => new TaskRepository(
            $c->get(Connection::class)
        ));

        $container->singleton(TaskService::class, static fn (Container $c): TaskService => new TaskService(
            $c->get(TaskRepository::class),
            $c->get(UserRepository::class),
            $c->get(AccessControlInterface::class),
            $c->get(Validator::class),
            $c->get(AuditLogger::class),
            $c->get(Config::class)
        ));

        $container->singleton(TaskController::class, static fn (Container $c): TaskController => new TaskController(
            $c->get(TaskService::class),
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
