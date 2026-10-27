<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Product\Models\Product;

/**
 * Tells the taxonomy (M02) that a category holds products, so it can be neither deleted
 * nor given children. Soft-deleted products do not count.
 */
class CategoryInUseChecker implements CategoryInUse
{
    public function isInUse(int $categoryId): bool
    {
        return Product::query()->where('catalog_category_id', $categoryId)->exists();
    }
}
