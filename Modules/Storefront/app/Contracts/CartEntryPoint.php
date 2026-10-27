<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

/**
 * Where the product page's "Add to Cart" and "Buy Now" forms post ({variant_id, quantity}).
 * The Storefront binds a null entry point (buttons hidden); the Cart module (M10) rebinds it.
 */
interface CartEntryPoint
{
    public function isAvailable(): bool;

    public function addUrl(): ?string;

    public function buyNowUrl(): ?string;
}
