<?php

declare(strict_types=1);

namespace Modules\Storefront\Listeners;

use Modules\Product\Events\ProductBlocked;
use Modules\Storefront\Services\Search\SearchIndexer;

/**
 * A blocked product leaves search at once (ProductStatusChanged covers it too; removing is
 * idempotent).
 */
class RemoveProductFromIndex
{
    public function __construct(protected SearchIndexer $indexer) {}

    public function handle(ProductBlocked $event): void
    {
        try {
            $this->indexer->remove($event->product->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
