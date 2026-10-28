<?php

declare(strict_types=1);

namespace Modules\Cart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Cart\Database\Factories\CartItemFactory;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;

/**
 * One variant in a cart, either in the cart itself or saved for later.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int $variant_id
 * @property int $seller_id
 * @property int $quantity
 * @property Money $price_at_add
 * @property int $is_saved_for_later
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CartItem extends MarketplaceModel
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $table = 'cart_item';

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'seller_id',
        'quantity',
        'price_at_add',
        'is_saved_for_later',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'seller_id' => 'integer',
            'quantity' => 'integer',
            'price_at_add' => MoneyCast::class,
            'is_saved_for_later' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @param  Builder<CartItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_saved_for_later'), config('core.no'));
    }

    /**
     * @param  Builder<CartItem>  $query
     */
    public function scopeSavedForLater(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_saved_for_later'), config('core.yes'));
    }

    public function isSavedForLater(): bool
    {
        return $this->is_saved_for_later === (int) config('core.yes');
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
