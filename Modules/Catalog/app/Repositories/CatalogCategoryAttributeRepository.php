<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Core\Repositories\BaseRepository;

interface CatalogCategoryAttributeRepository extends BaseRepository
{
    /**
     * Mapping rows defined on the category itself, by sort order.
     *
     * @return Collection<int, CatalogCategoryAttribute>
     */
    public function forCategory(int $categoryId): Collection;

    /**
     * Mapping rows of several categories (an ancestor path), any order.
     *
     * @param  list<int>  $categoryIds
     * @return Collection<int, CatalogCategoryAttribute>
     */
    public function forCategories(array $categoryIds): Collection;

    /**
     * Replace the category's own mapping rows with $rows, keyed by attribute id.
     *
     * @param  array<int, array{is_variant_axis: int, is_filterable: int, is_required: int, show_on_card: int, sort_order: int, allowed_option_ids: list<int>|null}>  $rows
     */
    public function sync(int $categoryId, array $rows): void;
}
