<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repositories;

use App\Infrastructure\Database\Connection;
use App\Modules\Catalog\Models\Product;
use App\Support\Enums\ProductType;

/**
 * PDO repository for catalog properties (lodges, camps, accommodation).
 */
final class ProductRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<Product>
     */
    public function findActive(
        ?ProductType $type = null,
        ?string $search = null,
        ?int $minGuests = null,
        int $limit = 9,
        int $offset = 0
    ): array {
        [$whereSql, $params] = $this->buildFilterClause($type, $search, $minGuests);
        $params['limit'] = max(1, $limit);
        $params['offset'] = max(0, $offset);

        $rows = $this->connection->fetchAll(
            'SELECT * FROM products ' . $whereSql . ' ORDER BY is_featured DESC, name ASC LIMIT :limit OFFSET :offset',
            $params
        );

        return array_map(
            static fn (array $row): Product => Product::fromRow($row),
            $rows
        );
    }

    public function countActive(?ProductType $type = null, ?string $search = null, ?int $minGuests = null): int
    {
        [$whereSql, $params] = $this->buildFilterClause($type, $search, $minGuests);
        $row = $this->connection->fetchOne('SELECT COUNT(*) AS total FROM products ' . $whereSql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<Product>
     */
    public function findFeatured(int $limit = 4): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT * FROM products WHERE is_active = 1 AND is_featured = 1 ORDER BY id ASC LIMIT :limit',
            ['limit' => max(1, $limit)]
        );

        return array_map(
            static fn (array $row): Product => Product::fromRow($row),
            $rows
        );
    }

    /**
     * @return list<Product>
     */
    public function findAllActiveForSelect(): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT * FROM products WHERE is_active = 1 ORDER BY type ASC, name ASC'
        );

        return array_map(
            static fn (array $row): Product => Product::fromRow($row),
            $rows
        );
    }

    public function findBySlugAndType(string $slug, ProductType $type): ?Product
    {
        $row = $this->connection->fetchOne(
            'SELECT * FROM products WHERE slug = :slug AND type = :type AND is_active = 1 LIMIT 1',
            [
                'slug' => $slug,
                'type' => $type->value,
            ]
        );

        return $row !== null ? Product::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Product
    {
        $row = $this->connection->fetchOne(
            'SELECT * FROM products WHERE slug = :slug AND is_active = 1 LIMIT 1',
            ['slug' => $slug]
        );

        return $row !== null ? Product::fromRow($row) : null;
    }

    public function findById(int $id): ?Product
    {
        $row = $this->connection->fetchOne(
            'SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1',
            ['id' => $id]
        );

        return $row !== null ? Product::fromRow($row) : null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterClause(?ProductType $type, ?string $search, ?int $minGuests): array
    {
        $conditions = ['is_active = 1'];
        $params = [];

        if ($type !== null) {
            $conditions[] = 'type = :type';
            $params['type'] = $type->value;
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $conditions[] = '(name LIKE :search_name OR location LIKE :search_loc'
                . ' OR short_summary LIKE :search_summary)';
            $params['search_name'] = $term;
            $params['search_loc'] = $term;
            $params['search_summary'] = $term;
        }

        if ($minGuests !== null && $minGuests > 0) {
            $conditions[] = 'max_guests >= :min_guests';
            $params['min_guests'] = $minGuests;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
