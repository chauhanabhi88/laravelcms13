<?php

namespace Modules\Catalog\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\Catalog\Repositories\CatalogCategoryAttributeRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentCatalogCategoryAttributeRepository extends EloquentBaseRepository implements CatalogCategoryAttributeRepository
{
    public function forCategory(int $categoryId): Collection
    {
        return $this->model->newQuery()
            ->where('catalog_category_id', $categoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function forCategories(array $categoryIds): Collection
    {
        if ($categoryIds === []) {
            return new Collection;
        }

        return $this->model->newQuery()->whereIn('catalog_category_id', $categoryIds)->get();
    }

    public function sync(int $categoryId, array $rows): void
    {
        $this->model->newQuery()
            ->where('catalog_category_id', $categoryId)
            ->when($rows !== [], fn ($query) => $query->whereNotIn('attribute_id', array_keys($rows)))
            ->delete();

        foreach ($rows as $attributeId => $row) {
            $this->model->newQuery()->updateOrCreate(
                ['catalog_category_id' => $categoryId, 'attribute_id' => (int) $attributeId],
                $row,
            );
        }
    }
}
