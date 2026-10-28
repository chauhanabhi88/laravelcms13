<?php

declare(strict_types=1);

namespace Modules\Cart\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Models\ProductVariant;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'quantity' => 1,
            'is_saved_for_later' => (int) config('core.no'),
        ];
    }

    /**
     * Point the line at a variant, copying its product, seller and current price.
     */
    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn (): array => [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'seller_id' => $variant->seller_id,
            'price_at_add' => $variant->price,
        ]);
    }

    public function savedForLater(): static
    {
        return $this->state(['is_saved_for_later' => (int) config('core.yes')]);
    }
}
