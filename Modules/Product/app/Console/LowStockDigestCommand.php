<?php

declare(strict_types=1);

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Notifications\LowStockDigest;
use Modules\Product\Support\OptionLabels;
use Modules\Seller\Models\SellerUser;

/**
 * Emails each seller the variants flagged low on stock since the last run and still low
 * (FR-SCT-007), to the owner and catalog staff, then clears the flags. Scheduled daily by the
 * Cron module (migration 2026_11_05_000004).
 */
class LowStockDigestCommand extends Command
{
    protected $signature = 'product:low-stock-digest';

    protected $description = 'Email suppliers the variants that ran low on stock since the last digest';

    public function handle(OptionLabels $labels): int
    {
        $flagged = ProductVariant::query()
            ->whereNotNull('low_stock_flagged_at')
            ->with(['product.translations', 'attributeValues'])
            ->orderBy('seller_id')
            ->orderBy('id')
            ->get();

        if ($flagged->isEmpty()) {
            $this->info('No low-stock variants to report.');

            return self::SUCCESS;
        }

        $names = $labels->for($flagged->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_option_id'))->all());
        $sent = 0;

        foreach ($flagged->groupBy('seller_id') as $sellerId => $variants) {
            $lines = $variants
                ->filter(fn (ProductVariant $variant): bool => $variant->product !== null && $variant->isEnabled() && $variant->isLowStock())
                ->map(fn (ProductVariant $variant): array => [
                    'name' => trim((string) $variant->product->name.' '.OptionLabels::combination($variant->attributeValues->pluck('attribute_option_id', 'attribute_id')->map(fn ($id): int => (int) $id)->all(), $names)),
                    'sku' => $variant->seller_sku,
                    'available' => $variant->available,
                    'threshold' => $variant->low_stock_threshold,
                ])
                ->values()
                ->all();

            if ($lines !== []) {
                $recipients = SellerUser::query()
                    ->where('seller_id', $sellerId)
                    ->where('status', config('core.enabled'))
                    ->whereIn('role', ['owner', 'catalog'])
                    ->get();

                foreach ($recipients as $recipient) {
                    try {
                        $recipient->notify(new LowStockDigest($lines));
                    } catch (\Throwable $e) {
                        Log::warning('Low-stock digest failed', ['seller_user' => $recipient->id, 'error' => $e->getMessage()]);
                    }
                }

                $sent++;
            }
        }

        ProductVariant::query()->whereKey($flagged->modelKeys())->update(['low_stock_flagged_at' => null]);

        $this->info("Low-stock digest sent to {$sent} seller(s).");

        return self::SUCCESS;
    }
}
