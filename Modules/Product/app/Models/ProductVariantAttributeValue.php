<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;

/**
 * One variant-axis option of a variant. Composite key (variant_id, attribute_id): rows are
 * only inserted and deleted, never updated in place.
 *
 * @property int $variant_id
 * @property int $attribute_id
 * @property int $attribute_option_id
 */
class ProductVariantAttributeValue extends MarketplaceModel
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'product_variant_attribute_value';

    protected $primaryKey = 'variant_id';

    protected $fillable = ['variant_id', 'attribute_id', 'attribute_option_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'attribute_id' => 'integer',
            'attribute_option_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
