<?php

declare(strict_types=1);

namespace Modules\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\ProductVariant;

/**
 * A variant's available stock fell to or below its low-stock threshold (fired once, when
 * it crosses downward). M05 / M09 alert the seller.
 */
class VariantLowStock implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProductVariant $variant,
        public int $available,
    ) {}
}
