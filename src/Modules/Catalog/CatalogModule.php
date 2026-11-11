<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Core\Config;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Catalog\Controllers\CatalogController;
use App\Modules\Catalog\Controllers\PublicPageController;
use App\Modules\Catalog\Controllers\SitemapController;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\Services\CatalogService;
use Monolog\Logger;

/**
 * Catalog Module — owns public storefront pages, SEO property catalog, and sitemap.xml.
 */
final class CatalogModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(
            ProductRepository::class,
            static fn (Container $c): ProductRepository => new ProductRepository(
                $c->get(Connection::class)
            )
        );

        $container->singleton(CatalogService::class, static fn (Container $c): CatalogService => new CatalogService(
            $c->get(ProductRepository::class),
            $c->get(Config::class)
        ));

        $container->singleton(
            CatalogController::class,
            static fn (Container $c): CatalogController => new CatalogController(
                $c->get(ViewFactory::class),
                $c->get(CatalogService::class),
                $c->get(Config::class)
            )
        );

        $container->singleton(
            PublicPageController::class,
            static fn (Container $c): PublicPageController => new PublicPageController(
                $c->get(ViewFactory::class),
                $c->get(CatalogService::class),
                $c->get(Validator::class),
                $c->get(Session::class),
                $c->get(Logger::class),
                $c->get(AuditLogger::class),
                $c->get(Config::class)
            )
        );

        $container->singleton(
            SitemapController::class,
            static fn (Container $c): SitemapController => new SitemapController(
                $c->get(CatalogService::class),
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
