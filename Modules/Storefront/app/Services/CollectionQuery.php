<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Modules\Catalog\Services\CategoryTreeService;
use Modules\Storefront\Models\ProductCollection;

/**
 * Maps a collection onto listing constraints: its rules for a "rule" collection, its product
 * id list for a "manual" one.
 */
class CollectionQuery
{
    public function __construct(protected CategoryTreeService $tree) {}

    /**
     * ListingQuery fixed values for the collection (constraints + default sort).
     *
     * @return array{constraints: array<string, mixed>, sort: string}
     */
    public function fixed(ProductCollection $collection): array
    {
        return ['constraints' => $this->constraints($collection), 'sort' => $collection->listingSort()];
    }

    /**
     * @return array<string, mixed>
     */
    public function constraints(ProductCollection $collection): array
    {
        if ($collection->isManual()) {
            return ['product_ids' => $collection->manualProductIds()];
        }

        $rules = (array) $collection->rules;
        $nodes = $this->tree->nodesById();
        $constraints = [];

        $paths = array_values(array_filter(array_map(
            fn ($id): ?string => $nodes[(int) $id]['path'] ?? null,
            (array) ($rules['category_ids'] ?? []),
        )));
        if ($paths !== []) {
            $constraints['category_paths'] = $paths;
        }

        foreach (['max_price', 'min_discount', 'min_rating'] as $key) {
            if (is_numeric($rules[$key] ?? null)) {
                $constraints[$key] = $rules[$key] + 0;
            }
        }

        foreach (['brand_ids', 'seller_ids'] as $key) {
            $ids = array_values(array_filter(array_map('intval', (array) ($rules[$key] ?? []))));
            if ($ids !== []) {
                $constraints[$key] = $ids;
            }
        }

        return $constraints;
    }
}
