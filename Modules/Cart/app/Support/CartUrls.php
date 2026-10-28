<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Illuminate\Support\Facades\Route;
use Modules\Cart\Models\Cart;

/**
 * Cart page and checkout links. Checkout (M12) registers "checkout.index"; until then the
 * cart's "Continue" has nowhere to go and Buy Now shows its own cart page.
 */
final class CartUrls
{
    public const CHECKOUT_ROUTE = 'checkout.index';

    public static function cart(?string $type = null): string
    {
        $url = frontRoute('cart.index');

        return $type === Cart::TYPE_BUY_NOW ? $url.'?cart='.Cart::TYPE_BUY_NOW : $url;
    }

    public static function checkout(string $type = Cart::TYPE_STANDARD): ?string
    {
        if (! Route::has(self::CHECKOUT_ROUTE)) {
            return null;
        }

        $url = frontRoute(self::CHECKOUT_ROUTE);

        return $type === Cart::TYPE_BUY_NOW ? $url.'?cart='.Cart::TYPE_BUY_NOW : $url;
    }

    /**
     * Where Buy Now lands: checkout when it exists, else the Buy Now cart page.
     */
    public static function afterBuyNow(): string
    {
        return self::checkout(Cart::TYPE_BUY_NOW) ?? self::cart(Cart::TYPE_BUY_NOW);
    }
}
