<?php

declare(strict_types=1);

namespace Modules\Review\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Review\Events\ReviewStatusChanged;
use Modules\Review\Services\ReviewAggregateService;

/**
 * A review entered or left "published" (or a published review's rating changed): recompute
 * the product's and seller's ratings, which re-indexes the product (plan M16 §5). Queued after
 * the commit; the storefront catches up within a minute with a running worker.
 */
class RecalculateAggregatesOnPublish implements ShouldQueue
{
    public function __construct(private readonly ReviewAggregateService $aggregates) {}

    public function handle(ReviewStatusChanged $event): void
    {
        if ($event->affectsAggregates()) {
            $this->aggregates->recalculate($event->productId);
        }
    }

    public function shouldQueue(ReviewStatusChanged $event): bool
    {
        return $event->affectsAggregates();
    }
}
