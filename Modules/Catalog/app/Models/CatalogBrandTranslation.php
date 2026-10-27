<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Modules\Core\Models\MarketplaceModel;

class CatalogBrandTranslation extends MarketplaceModel
{
    protected $table = 'catalog_brand_translation';

    protected $fillable = ['catalog_brand_id', 'locale', 'name', 'description'];
}
