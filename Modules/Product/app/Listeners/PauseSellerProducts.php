<?php

declare(strict_types=1);

namespace Modules\Product\Listeners;

use Modules\Core\Cache\Concerns\FlushesEntityCache;
use Modules\Product\Models\Product;
use Modules\Seller\Events\SellerSuspended;

/**
 * A suspended seller's products leave the storefront (FR-SEL-007). Only seller_active
 * changes, so the seller's own live / paused choice survives the suspension.
 */
class PauseSellerProducts
{
    use FlushesEntityCache;

    public function handle(SellerSuspended $event): void
    {
        Product::query()->withTrashed()->where('seller_id', $event->seller->id)->toBase()->update(['seller_active' => config('core.no')]);

        $this->flushCacheFor(config('product.cache.product'));
    }
}
