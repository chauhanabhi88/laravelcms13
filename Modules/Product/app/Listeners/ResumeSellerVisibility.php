<?php

declare(strict_types=1);

namespace Modules\Product\Listeners;

use Modules\Core\Cache\Concerns\FlushesEntityCache;
use Modules\Product\Models\Product;
use Modules\Seller\Events\SellerReactivated;

/**
 * A reactivated seller's products are visible again; products the seller had paused stay paused.
 */
class ResumeSellerVisibility
{
    use FlushesEntityCache;

    public function handle(SellerReactivated $event): void
    {
        Product::query()->withTrashed()->where('seller_id', $event->seller->id)->toBase()->update(['seller_active' => config('core.yes')]);

        $this->flushCacheFor(config('product.cache.product'));
    }
}
