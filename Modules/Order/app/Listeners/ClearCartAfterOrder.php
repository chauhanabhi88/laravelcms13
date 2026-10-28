<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Order\Events\OrderPlaced;

/**
 * The purchased lines leave the cart once the order is written (saved-for-later lines and
 * lines added meanwhile stay). A Buy Now cart is dropped entirely.
 */
class ClearCartAfterOrder
{
    public function handle(OrderPlaced $event): void
    {
        if ($event->cartId === null) {
            return;
        }

        if ($event->cartType === Cart::TYPE_BUY_NOW) {
            Cart::query()->whereKey($event->cartId)->where('customer_id', $event->order->customer_id)->delete();

            return;
        }

        if ($event->cartItemIds !== []) {
            CartItem::query()->where('cart_id', $event->cartId)->whereIn('id', $event->cartItemIds)->delete();
        }

        Cart::query()->whereKey($event->cartId)->update(['last_activity_at' => now(), 'reminded_at' => null]);
    }
}
