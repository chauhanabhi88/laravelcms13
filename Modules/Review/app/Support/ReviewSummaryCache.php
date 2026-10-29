<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The product page's review summary, cached per product as a plain array (file-cache safe).
 * Forgotten whenever the product's visible reviews change: aggregates, edits, replies,
 * moderation.
 */
final class ReviewSummaryCache
{
    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function remember(int $productId, Closure $build): array
    {
        $minutes = (int) config('review.summary.cache_minutes', 10);

        if ($minutes <= 0) {
            return $build();
        }

        $cached = Cache::remember(self::key($productId), now()->addMinutes($minutes), $build);

        return is_array($cached) ? $cached : $build();
    }

    public static function forget(int $productId): void
    {
        Cache::forget(self::key($productId));
    }

    protected static function key(int $productId): string
    {
        return 'review:summary:'.$productId;
    }
}
