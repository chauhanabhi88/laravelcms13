<?php

declare(strict_types=1);

namespace Modules\Review\Listeners;

use Modules\Customer\Events\CustomerAnonymised;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ReviewSummaryCache;

/**
 * A deleted account (DPDP, FR-CUS-008) keeps its reviews — they describe the product — but
 * not the author's name: it becomes the generic "Verified Buyer".
 */
class AnonymiseReviewAuthor
{
    public function handle(CustomerAnonymised $event): void
    {
        $productIds = ProductReview::query()->forCustomer($event->customerId)->distinct()->pluck('product_id');

        if ($productIds->isEmpty()) {
            return;
        }

        ProductReview::query()->forCustomer($event->customerId)->toBase()->update(['author_name' => trans('review::front.card.anonymous')]);

        foreach ($productIds as $productId) {
            ReviewSummaryCache::forget((int) $productId);
        }
    }
}
