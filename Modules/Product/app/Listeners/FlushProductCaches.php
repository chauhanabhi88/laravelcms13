<?php

declare(strict_types=1);

namespace Modules\Product\Listeners;

use Modules\Core\Cache\Concerns\FlushesEntityCache;

/**
 * Drops the product repository read caches after a moderation decision or a change of the
 * listing aggregates.
 */
class FlushProductCaches
{
    use FlushesEntityCache;

    public function handle(object $event): void
    {
        $this->flushCacheFor(config('product.cache.product'));
        $this->flushCacheFor(config('product.cache.product_qc'));
    }
}
