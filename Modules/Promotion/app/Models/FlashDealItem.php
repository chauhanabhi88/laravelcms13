<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Promotion\Database\Factories\FlashDealItemFactory;

/**
 * One variant in a flash deal: its deal price and the optional cap on deal units. "sold" only
 * changes under a row lock at place-order (up) and on cancellation (down).
 *
 * @property int $id
 * @property int $flash_deal_id
 * @property int $product_id
 * @property int $variant_id
 * @property Money $deal_price
 * @property int|null $stock_cap
 * @property int $sold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashDealItem extends MarketplaceModel
{
    /** @use HasFactory<FlashDealItemFactory> */
    use HasFactory;

    protected $table = 'flash_deal_item';

    protected $fillable = ['flash_deal_id', 'product_id', 'variant_id', 'deal_price', 'stock_cap', 'sold'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'flash_deal_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'deal_price' => MoneyCast::class,
            'stock_cap' => 'integer',
            'sold' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FlashDeal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(FlashDeal::class, 'flash_deal_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Deal units left (null = no cap).
     */
    public function remaining(): ?int
    {
        return $this->stock_cap === null ? null : max(0, $this->stock_cap - $this->sold);
    }

    protected static function newFactory(): FlashDealItemFactory
    {
        return FlashDealItemFactory::new();
    }
}
