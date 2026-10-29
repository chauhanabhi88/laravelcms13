<?php

declare(strict_types=1);

namespace Modules\Review\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Review\Models\ProductReview;

/**
 * A review was created, re-moderated, moderated or deleted (fired after commit). Carries ids,
 * not the model, so a queued listener still runs after the review is deleted.
 *
 * $from is null for a new review; $to is ProductReview::DELETED for a deleted one. A published
 * review whose rating changed fires published → published.
 */
class ReviewStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $reviewId,
        public readonly int $productId,
        public readonly ?string $from,
        public readonly string $to,
    ) {}

    /**
     * Whether the published ratings (product / seller aggregates) may have changed.
     */
    public function affectsAggregates(): bool
    {
        return $this->from === ProductReview::STATUS_PUBLISHED || $this->to === ProductReview::STATUS_PUBLISHED;
    }
}
