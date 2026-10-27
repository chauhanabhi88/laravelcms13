<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Product\Database\Factories\ProductVariantFactory;

/**
 * A sellable SKU of a product. stock / reserved change only through InventoryService.
 *
 * @property int $id
 * @property int $product_id
 * @property int $seller_id
 * @property string $seller_sku
 * @property string $sku
 * @property string $combination_key
 * @property Money $mrp
 * @property Money $price
 * @property int $stock
 * @property int $reserved
 * @property-read int $available
 * @property int $low_stock_threshold
 * @property Carbon|null $low_stock_flagged_at
 * @property int $weight_grams
 * @property string|null $length_cm
 * @property string|null $breadth_cm
 * @property string|null $height_cm
 * @property string|null $barcode
 * @property int $status
 * @property int $position
 */
class ProductVariant extends MarketplaceModel
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    public const DEFAULT_COMBINATION = 'default';

    protected $table = 'product_variant';

    protected $fillable = [
        'product_id',
        'seller_id',
        'seller_sku',
        'sku',
        'combination_key',
        'mrp',
        'price',
        'stock',
        'reserved',
        'low_stock_threshold',
        'low_stock_flagged_at',
        'weight_grams',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'barcode',
        'status',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'seller_id' => 'integer',
            'mrp' => MoneyCast::class,
            'price' => MoneyCast::class,
            'stock' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
            'low_stock_flagged_at' => 'datetime',
            'weight_grams' => 'integer',
            'length_cm' => 'decimal:1',
            'breadth_cm' => 'decimal:1',
            'height_cm' => 'decimal:1',
            'status' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * The variant-axis options defining this variant.
     *
     * @return HasMany<ProductVariantAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class, 'variant_id');
    }

    /**
     * Sellable units: stock not held by orders, never negative.
     *
     * @return Attribute<int, never>
     */
    protected function available(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) $this->stock - (int) $this->reserved));
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    public function isLowStock(): bool
    {
        return $this->available <= $this->low_stock_threshold;
    }

    /**
     * Sorted "attributeId:optionId|…" key of an axis selection ("default" with no axes).
     *
     * @param  array<int|string, int|string>  $options  attribute id => option id
     */
    public static function combinationKeyFor(array $options): string
    {
        if ($options === []) {
            return self::DEFAULT_COMBINATION;
        }

        $pairs = [];
        foreach ($options as $attributeId => $optionId) {
            $pairs[(int) $attributeId] = (int) $attributeId.':'.(int) $optionId;
        }
        ksort($pairs);

        return implode('|', $pairs);
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }
}
