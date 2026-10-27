<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\DTO\ReviewSummary;

/**
 * No reviews until the Review module (M16) rebinds ReviewSummaryProvider: the product page
 * shows "No ratings yet".
 */
class NullReviewSummaryProvider implements ReviewSummaryProvider
{
    public function forProduct(int $productId): ReviewSummary
    {
        return ReviewSummary::empty();
    }
}
