<?php

declare(strict_types=1);

namespace Modules\Catalog\Contracts;

/**
 * Tells the taxonomy whether products are attached to a category.
 *
 * Products may only sit on leaves, so a category in use can neither be deleted nor
 * receive children ("move products first"). The Product module (M04) binds the real
 * implementation; until then NullCategoryInUse reports every category as unused.
 */
interface CategoryInUse
{
    public function isInUse(int $categoryId): bool;
}
