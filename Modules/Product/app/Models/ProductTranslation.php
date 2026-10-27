<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Modules\Core\Models\MarketplaceModel;

class ProductTranslation extends MarketplaceModel
{
    protected $table = 'product_translation';

    protected $fillable = ['product_id', 'locale', 'name', 'description', 'highlights', 'meta_title', 'meta_description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'highlights' => 'array',
        ];
    }
}
