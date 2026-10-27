<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

use Modules\Storefront\DTO\ReviewSummary;

/**
 * Ratings & reviews summary for the product page (FR-PDP-008). The Storefront binds a null
 * provider (no ratings yet); the Review module (M16) rebinds it.
 */
interface ReviewSummaryProvider
{
    public function forProduct(int $productId): ReviewSummary;
}
