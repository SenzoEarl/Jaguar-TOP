<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireCsrfMiddleware;
use App\Modules\Catalog\Controllers\CatalogController;
use App\Modules\Catalog\Controllers\PublicPageController;
use App\Modules\Catalog\Controllers\SitemapController;

return static function (Router $router): void {
    $router->get('/', [PublicPageController::class, 'home']);
    $router->get('/about', [PublicPageController::class, 'about']);
    $router->get('/contact', [PublicPageController::class, 'contact']);
    $router->post('/contact', [PublicPageController::class, 'submitContact'], [RequireCsrfMiddleware::class]);

    $router->get('/stays', [CatalogController::class, 'listAll']);
    $router->get('/lodges', [CatalogController::class, 'listLodges']);
    $router->get('/lodges/{slug}', [CatalogController::class, 'detailLodge']);

    $router->get('/camps', [CatalogController::class, 'listCamps']);
    $router->get('/camps/{slug}', [CatalogController::class, 'detailCamp']);

    $router->get('/accommodation', [CatalogController::class, 'listAccommodation']);
    $router->get('/accommodation/{slug}', [CatalogController::class, 'detailAccommodation']);

    $router->get('/sitemap.xml', [SitemapController::class, 'index']);
};
