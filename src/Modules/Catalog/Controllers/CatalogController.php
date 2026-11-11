<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Catalog\Services\CatalogService;
use App\Support\Enums\ProductType;

/**
 * Serves public catalog category listings and SEO-rich property detail pages.
 */
final class CatalogController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly CatalogService $catalog,
        private readonly Config $config
    ) {
    }

    public function listAll(Request $request): Response
    {
        $rawType = (string) $request->query('type', '');
        $type = $rawType !== '' ? ProductType::fromSegmentOrValue($rawType) : null;
        return $this->renderListing($request, $type, '/stays');
    }

    public function listLodges(Request $request): Response
    {
        return $this->renderListing($request, ProductType::Lodge, '/lodges');
    }

    public function listCamps(Request $request): Response
    {
        return $this->renderListing($request, ProductType::Camp, '/camps');
    }

    public function listAccommodation(Request $request): Response
    {
        return $this->renderListing($request, ProductType::Accommodation, '/accommodation');
    }

    /**
     * @param array<string, string> $params
     */
    public function detailLodge(Request $request, array $params): Response
    {
        return $this->renderDetail(ProductType::Lodge, (string) ($params['slug'] ?? ''));
    }

    /**
     * @param array<string, string> $params
     */
    public function detailCamp(Request $request, array $params): Response
    {
        return $this->renderDetail(ProductType::Camp, (string) ($params['slug'] ?? ''));
    }

    /**
     * @param array<string, string> $params
     */
    public function detailAccommodation(Request $request, array $params): Response
    {
        return $this->renderDetail(ProductType::Accommodation, (string) ($params['slug'] ?? ''));
    }

    private function renderListing(Request $request, ?ProductType $type, string $canonicalPath): Response
    {
        $search = trim((string) $request->query('q', ''));
        $rawGuests = $request->query('guests');
        $minGuests = is_numeric($rawGuests) && (int) $rawGuests > 0 ? (int) $rawGuests : null;
        $rawPage = $request->query('page', 1);
        $page = is_numeric($rawPage) && (int) $rawPage > 0 ? (int) $rawPage : 1;

        $result = $this->catalog->listCatalog(
            $type,
            $search !== '' ? $search : null,
            $minGuests,
            $page
        );

        $brandName = $this->config->string('app.name', 'Wildcrest Lodges & Camps');
        $heading = $type !== null ? $type->pluralLabel() : 'All Lodges, Camps & Stays';
        $description = $type !== null
            ? sprintf(
                'Browse %s by %s across secluded reserves and mountain sanctuaries.',
                strtolower($type->pluralLabel()),
                $brandName
            )
            : sprintf('Explore luxury timber lodges, canvas bush camps, and forest suites at %s.', $brandName);

        $currency = $this->config->string('app.booking_currency', 'USD');
        $basePath = $this->config->string('app.base_path', '');

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'total' => $result['total'],
                'items' => array_map(static fn ($item): array => [
                    'slug' => $item->getSlug(),
                    'name' => $item->getName(),
                    'type' => $item->getType()->value,
                    'typeLabel' => $item->getType()->label(),
                    'location' => $item->getLocation(),
                    'shortSummary' => $item->getShortSummary(),
                    'priceFormatted' => format_money($item->getPricePerNight(), $currency),
                    'maxGuests' => $item->getMaxGuests(),
                    'bedrooms' => $item->getBedrooms(),
                    'imagePath' => app_url($item->getImagePath(), $basePath),
                    'imageAlt' => $item->getImageAlt(),
                    'detailUrl' => app_url($item->detailPath(), $basePath),
                ], $result['items']),
            ]);
        }

        $html = $this->views->render('public.catalog-list', [
            'pageTitle' => $heading . ' | ' . $brandName,
            'metaDescription' => $description,
            'canonicalPath' => $canonicalPath,
            'heading' => $heading,
            'activeType' => $type,
            'products' => $result['items'],
            'totalCount' => $result['total'],
            'currentPage' => $result['page'],
            'totalPages' => $result['total_pages'],
            'searchQuery' => $search,
            'minGuests' => $minGuests,
        ]);

        return Response::html($html);
    }

    private function renderDetail(ProductType $type, string $slug): Response
    {
        $product = $this->catalog->getDetail($type, $slug);
        $brandName = $this->config->string('app.name', 'Wildcrest Lodges & Camps');

        $jsonLd = $product->toJsonLd(
            $this->config->string('app.url', 'http://127.0.0.1:8000'),
            $this->config->string('app.base_path', ''),
            $this->config->string('app.booking_currency', 'USD'),
            $brandName
        );

        $html = $this->views->render('public.catalog-detail', [
            'pageTitle' => $product->getName() . ' | ' . $brandName,
            'metaDescription' => $product->getShortSummary(),
            'canonicalPath' => $product->detailPath(),
            'ogImage' => $product->getImagePath(),
            'product' => $product,
            'jsonLd' => $jsonLd,
        ]);

        return Response::html($html);
    }
}
