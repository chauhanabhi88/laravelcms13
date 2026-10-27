<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Modules\Core\Models\MarketplaceModel;

class ProductCollectionTranslation extends MarketplaceModel
{
    protected $table = 'product_collection_translation';

    protected $fillable = ['product_collection_id', 'locale', 'name', 'description'];

    protected static function booted(): void
    {
        static::saved(fn () => HomeSection::flushHomeCache());
    }
}
