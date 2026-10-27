<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Modules\Core\Models\MarketplaceModel;

class CatalogCategoryTranslation extends MarketplaceModel
{
    protected $table = 'catalog_category_translation';

    protected $fillable = ['catalog_category_id', 'locale', 'name', 'description', 'meta_title', 'meta_description', 'meta_keywords'];
}
