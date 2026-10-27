<?php

declare(strict_types=1);

namespace Modules\Product\Listeners;

use Modules\Product\Events\VariantLowStock;
use Modules\Product\Models\ProductVariant;

/**
 * Remembers that a variant ran low (FR-SCT-007); the daily LowStockDigest lists the flagged
 * variants and clears the flag.
 */
class FlagLowStockVariant
{
    public function handle(VariantLowStock $event): void
    {
        ProductVariant::query()
            ->whereKey($event->variant->id)
            ->whereNull('low_stock_flagged_at')
            ->update(['low_stock_flagged_at' => now()]);
    }
}
