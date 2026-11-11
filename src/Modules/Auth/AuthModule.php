<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\CurrentUserProviderInterface;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Repositories\LoginAttemptRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\AccessControl;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\RateLimiterService;
use Monolog\Logger;

/**
 * Auth Module — owns authentication, brute-force protection, and RBAC AccessControl.
 */
final class AuthModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Auth';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository(
            $c->get(Connection::class)
        ));

        $container->singleton(
            LoginAttemptRepository::class,
            static fn (Container $c): LoginAttemptRepository => new LoginAttemptRepository(
                $c->get(Connection::class)
            )
        );

        $container->singleton(AccessControl::class, static fn (Container $c): AccessControl => new AccessControl(
            $c->get(Config::class)
        ));
        $container->singleton(
            AccessControlInterface::class,
            static fn (Container $c): AccessControlInterface => $c->get(AccessControl::class)
        );

        $container->singleton(
            RateLimiterService::class,
            static fn (Container $c): RateLimiterService => new RateLimiterService(
                $c->get(LoginAttemptRepository::class),
                $c->get(Config::class),
                $c->get(Logger::class),
                $c->get(AuditLogger::class)
            )
        );

        $container->singleton(AuthService::class, static fn (Container $c): AuthService => new AuthService(
            $c->get(UserRepository::class),
            $c->get(RateLimiterService::class),
            $c->get(Session::class),
            $c->get(Csrf::class),
            $c->get(Config::class),
            $c->get(AuditLogger::class)
        ));
        $container->singleton(
            CurrentUserProviderInterface::class,
            static fn (Container $c): CurrentUserProviderInterface => $c->get(AuthService::class)
        );

        $container->singleton(AuthController::class, static fn (Container $c): AuthController => new AuthController(
            $c->get(ViewFactory::class),
            $c->get(AuthService::class),
            $c->get(AccessControlInterface::class),
            $c->get(Validator::class),
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
