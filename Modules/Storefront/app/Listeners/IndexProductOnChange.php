<?php

declare(strict_types=1);

namespace Modules\Storefront\Listeners;

use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Storefront\Services\Search\SearchIndexer;

/**
 * Re-indexes a product when it changes status (live, paused, blocked…), when QC approves a
 * live product's content edits, or when its price / stock / image aggregates change.
 *
 * Runs synchronously after the commit (the events are ShouldDispatchAfterCommit): indexing
 * one product is a handful of queries and needs no queue worker. A failure is reported, never
 * thrown, so the search index can never break a seller or admin action; storefront:reindex
 * repairs it.
 */
class IndexProductOnChange
{
    public function __construct(protected SearchIndexer $indexer) {}

    public function handle(ProductStatusChanged|ProductApproved|ProductAggregatesChanged $event): void
    {
        // A first approval also fires ProductStatusChanged (→ live); only content edits need this one.
        if ($event instanceof ProductApproved && ! $event->changesOnly) {
            return;
        }

        $productId = $event instanceof ProductAggregatesChanged ? $event->productId : $event->product->id;

        try {
            $this->indexer->index($productId);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
