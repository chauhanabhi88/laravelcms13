<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Modules\Catalog\Contracts\CategoryInUse;

/**
 * Used until the Product module exists: no category has products.
 */
class NullCategoryInUse implements CategoryInUse
{
    public function isInUse(int $categoryId): bool
    {
        return false;
    }
}
