<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Storefront\Contracts\CartEntryPoint;

/**
 * No cart until the Cart module (M10) rebinds CartEntryPoint. The product page hides its
 * buttons (and shows a "coming soon" note in local environments).
 */
class NullCartEntryPoint implements CartEntryPoint
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function addUrl(): ?string
    {
        return null;
    }

    public function buyNowUrl(): ?string
    {
        return null;
    }
}
