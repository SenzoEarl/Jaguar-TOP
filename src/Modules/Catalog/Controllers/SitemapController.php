<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Catalog\Services\CatalogService;

/**
 * Generates dynamic XML sitemap directly from active catalog records in MariaDB.
 */
final class SitemapController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly Config $config
    ) {
    }

    public function index(Request $request): Response
    {
        $baseUrl = rtrim($this->config->string('app.url', 'http://127.0.0.1:8000'), '/');
        $staticPaths = [
            '/',
            '/lodges',
            '/camps',
            '/accommodation',
            '/stays',
            '/about',
            '/contact',
        ];

        $urls = [];
        foreach ($staticPaths as $path) {
            $urls[] = [
                'loc' => $baseUrl . $path,
                'priority' => $path === '/' ? '1.0' : '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach ($this->catalog->getAllActiveForBookingOrSitemap() as $product) {
            $urls[] = [
                'loc' => $baseUrl . $product->detailPath(),
                'priority' => '0.9',
                'changefreq' => 'weekly',
            ];
        }

        $xmlLines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $entry) {
            $xmlLines[] = '  <url>';
            $xmlLines[] = '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            $xmlLines[] = '    <changefreq>' . $entry['changefreq'] . '</changefreq>';
            $xmlLines[] = '    <priority>' . $entry['priority'] . '</priority>';
            $xmlLines[] = '  </url>';
        }

        $xmlLines[] = '</urlset>';

        return Response::xml(implode("\n", $xmlLines) . "\n");
    }
}
