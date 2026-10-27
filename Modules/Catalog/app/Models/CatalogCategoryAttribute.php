<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attribute\Models\Attribute;
use Modules\Core\Models\MarketplaceModel;

/**
 * One attribute mapped onto one category. Descendants inherit it unless they map the
 * same attribute themselves (CategoryAttributeResolver).
 *
 * @property int $id
 * @property int $catalog_category_id
 * @property int $attribute_id
 * @property int $is_variant_axis
 * @property int $is_filterable
 * @property int $is_required
 * @property int $show_on_card
 * @property int $sort_order
 * @property list<int>|null $allowed_option_ids
 */
class CatalogCategoryAttribute extends MarketplaceModel
{
    /**
     * Yes/No flags of a mapping row (1 = yes, 2 = no).
     */
    public const FLAGS = ['is_variant_axis', 'is_filterable', 'is_required', 'show_on_card'];

    protected $table = 'catalog_category_attribute';

    protected $fillable = [
        'catalog_category_id',
        'attribute_id',
        'is_variant_axis',
        'is_filterable',
        'is_required',
        'show_on_card',
        'sort_order',
        'allowed_option_ids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_category_id' => 'integer',
            'attribute_id' => 'integer',
            'is_variant_axis' => 'integer',
            'is_filterable' => 'integer',
            'is_required' => 'integer',
            'show_on_card' => 'integer',
            'sort_order' => 'integer',
            'allowed_option_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
}
