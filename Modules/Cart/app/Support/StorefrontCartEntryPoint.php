<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Modules\Storefront\Contracts\CartEntryPoint;

/**
 * Where the product page's Add to Cart and Buy Now buttons post (bound over the Storefront's
 * null entry point).
 */
class StorefrontCartEntryPoint implements CartEntryPoint
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function addUrl(): ?string
    {
        return frontRoute('cart.add');
    }

    public function buyNowUrl(): ?string
    {
        return frontRoute('cart.buy_now');
    }
}
