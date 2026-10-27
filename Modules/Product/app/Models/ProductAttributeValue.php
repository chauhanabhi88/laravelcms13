<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Modules\Core\Models\MarketplaceModel;

/**
 * A specification attribute value: an option (one row per selected option) or free text.
 *
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property int|null $attribute_option_id
 * @property string|null $value_text
 */
class ProductAttributeValue extends MarketplaceModel
{
    public $timestamps = false;

    protected $table = 'product_attribute_value';

    protected $fillable = ['product_id', 'attribute_id', 'attribute_option_id', 'value_text'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'attribute_id' => 'integer',
            'attribute_option_id' => 'integer',
        ];
    }
}
