<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Core\Config;
use App\Exceptions\NotFoundException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Support\Enums\ProductType;

/**
 * Catalog business service for storefront browsing, category filtering, and SEO detail lookup.
 */
final class CatalogService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly Config $config
    ) {
    }

    /**
     * Retrieve paginated products for public listings.
     *
     * // [FUTURE:CACHING] Wrap catalog queries behind a PSR-16/Redis cache adapter here.
     * // [FUTURE:SEARCH] Swap SQL LIKE filtering for Meilisearch/Elasticsearch query adapter here.
     *
     * @return array{
     *     items: list<Product>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public function listCatalog(
        ?ProductType $type = null,
        ?string $search = null,
        ?int $minGuests = null,
        int $page = 1
    ): array {
        $perPage = max(1, $this->config->int('app.catalog_per_page', 9));
        $currentPage = max(1, $page);
        $offset = ($currentPage - 1) * $perPage;

        $total = $this->products->countActive($type, $search, $minGuests);
        $items = $this->products->findActive($type, $search, $minGuests, $perPage, $offset);
        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'total' => $total,
            'page' => min($currentPage, $totalPages),
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return list<Product>
     */
    public function getFeaturedStays(int $limit = 4): array
    {
        return $this->products->findFeatured($limit);
    }

    public function getDetail(ProductType $type, string $slug): Product
    {
        $product = $this->products->findBySlugAndType($slug, $type);
        if ($product === null) {
            throw new NotFoundException('The requested property could not be found.');
        }

        return $product;
    }

    /**
     * @return list<Product>
     */
    public function getAllActiveForBookingOrSitemap(): array
    {
        return $this->products->findAllActiveForSelect();
    }
}
