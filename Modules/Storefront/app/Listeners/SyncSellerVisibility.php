<?php

declare(strict_types=1);

namespace Modules\Storefront\Listeners;

use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerSuspended;
use Modules\Storefront\Services\Search\SearchIndexer;

/**
 * Hides or shows a seller's indexed products after a suspension or reactivation, in one
 * statement. The Product module's listener (registered first, lower module priority) has
 * already updated product.seller_active.
 */
class SyncSellerVisibility
{
    public function __construct(protected SearchIndexer $indexer) {}

    public function handle(SellerSuspended|SellerReactivated $event): void
    {
        try {
            $this->indexer->syncSellerVisibility($event->seller->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
