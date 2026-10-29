<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ReviewSummaryCache;
use Modules\Seller\Models\Seller;

/**
 * Denormalised ratings (FR-REV-003, plan M16 §5): from the published reviews only,
 * product.rating_avg (2 decimals), rating_count and rating_histogram, then the seller's
 * rating_avg / rating_count over all their published reviews (each review weighs the same).
 * Fires ProductAggregatesChanged so the storefront search index picks up the new rating.
 */
class ReviewAggregateService
{
    /**
     * @return bool whether the product's aggregates changed
     */
    public function recalculate(int $productId): bool
    {
        $changed = DB::transaction(function () use ($productId): bool {
            $product = Product::query()->withTrashed()->lockForUpdate()->find($productId, ['id', 'seller_id', 'rating_avg', 'rating_count', 'rating_histogram']);

            if ($product === null) {
                return false;
            }

            $values = $this->productValues($productId);
            $changed = number_format((float) $product->rating_avg, 2, '.', '') !== $values['rating_avg']
                || (int) $product->rating_count !== $values['rating_count']
                || $this->histogram($product->rating_histogram) !== $values['histogram'];

            if ($changed) {
                Product::query()->withTrashed()->whereKey($productId)->toBase()->update([
                    'rating_avg' => $values['rating_avg'],
                    'rating_count' => $values['rating_count'],
                    // Keys 5 … 1 are not a list, so this encodes as {"5": n, … "1": n}.
                    'rating_histogram' => json_encode($values['histogram']),
                ]);
            }

            $this->recalculateSeller((int) $product->seller_id);

            return $changed;
        });

        ReviewSummaryCache::forget($productId);

        if ($changed) {
            event(new ProductAggregatesChanged($productId, ['rating_avg', 'rating_count']));
        }

        return $changed;
    }

    /**
     * The seller's rating over every published review of their products.
     */
    public function recalculateSeller(int $sellerId): void
    {
        $seller = Seller::query()->lockForUpdate()->find($sellerId, ['id', 'rating_avg', 'rating_count']);

        if ($seller === null) {
            return;
        }

        $row = ProductReview::query()->published()->forSeller($sellerId)->toBase()->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        $count = (int) ($row->total ?? 0);
        $average = number_format($count > 0 ? round((float) $row->average, 2) : 0, 2, '.', '');

        if ($count !== (int) $seller->rating_count || $average !== number_format((float) $seller->rating_avg, 2, '.', '')) {
            Seller::query()->whereKey($sellerId)->toBase()->update(['rating_avg' => $average, 'rating_count' => $count]);
        }
    }

    /**
     * @return array{rating_avg: string, rating_count: int, histogram: array<int, int>}
     */
    public function productValues(int $productId): array
    {
        $counts = ProductReview::query()->published()->where('product_id', $productId)
            ->toBase()
            ->groupBy('rating')
            ->selectRaw('rating, COUNT(*) as total')
            ->pluck('total', 'rating');

        $histogram = [];
        $count = 0;
        $sum = 0;

        foreach ([5, 4, 3, 2, 1] as $stars) {
            $histogram[$stars] = (int) ($counts[$stars] ?? 0);
            $count += $histogram[$stars];
            $sum += $stars * $histogram[$stars];
        }

        return [
            'rating_avg' => number_format($count > 0 ? round($sum / $count, 2) : 0, 2, '.', ''),
            'rating_count' => $count,
            'histogram' => $histogram,
        ];
    }

    /**
     * A stored histogram (json or array) as stars 5 … 1 => count.
     *
     * @return array<int, int>
     */
    public function histogram(mixed $stored): array
    {
        $values = is_string($stored) ? (json_decode($stored, true) ?: []) : (array) $stored;
        $histogram = [];

        foreach ([5, 4, 3, 2, 1] as $stars) {
            $histogram[$stars] = (int) ($values[$stars] ?? $values[(string) $stars] ?? 0);
        }

        return $histogram;
    }
}
