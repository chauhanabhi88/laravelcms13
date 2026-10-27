<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Product page view counts (M07 §5.11). A hit increments a cache counter (bots skipped, one
 * count per visitor session per product per storefront.views.dedupe_minutes); the
 * storefront:flush-views command adds the collected counts to product.view_count every few
 * minutes, so a page view never writes to the product table.
 */
class ProductViewCounter
{
    public const SESSION_KEY = 'pdp_viewed';

    protected const PENDING_KEY = 'storefront.views.pending';

    protected const LOCK_KEY = 'storefront.views.lock';

    public function hit(Request $request, int $productId): bool
    {
        if ($this->isBot((string) $request->userAgent()) || $this->seenRecently($request, $productId)) {
            return false;
        }

        $this->increment($productId);

        return true;
    }

    public function isBot(string $userAgent): bool
    {
        return preg_match((string) config('storefront.views.bot_pattern'), trim($userAgent)) === 1;
    }

    /**
     * Views collected since the last flush (product id => count).
     *
     * @return array<int, int>
     */
    public function pending(): array
    {
        $counts = [];
        foreach (array_keys((array) Cache::get(self::PENDING_KEY, [])) as $productId) {
            $count = (int) Cache::get($this->countKey((int) $productId), 0);

            if ($count > 0) {
                $counts[(int) $productId] = $count;
            }
        }

        return $counts;
    }

    /**
     * Add the collected counts to product.view_count; returns the number of views written.
     */
    public function flush(): int
    {
        $productIds = $this->withLock(fn (): array => array_keys((array) Cache::pull(self::PENDING_KEY, []))) ?? [];
        $written = 0;

        foreach ($productIds as $productId) {
            Cache::forget($this->listedKey((int) $productId));
            $count = (int) Cache::pull($this->countKey((int) $productId), 0);

            if ($count > 0) {
                DB::table('product')->where('id', (int) $productId)->increment('view_count', $count);
                $written += $count;
            }
        }

        return $written;
    }

    /**
     * Whether this session already counted the product within the dedupe window (and, if
     * not, remember this view).
     */
    protected function seenRecently(Request $request, int $productId): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $now = now()->getTimestamp();
        $window = max(1, (int) config('storefront.views.dedupe_minutes', 60)) * 60;
        $seen = array_filter(
            (array) $request->session()->get(self::SESSION_KEY, []),
            fn ($timestamp): bool => is_int($timestamp) && $now - $timestamp < $window,
        );

        $recent = isset($seen[$productId]);

        if (! $recent) {
            $seen[$productId] = $now;
        }

        $request->session()->put(self::SESSION_KEY, $seen);

        return $recent;
    }

    protected function increment(int $productId): void
    {
        $ttl = (int) config('storefront.views.cache_ttl', 86400);

        Cache::add($this->countKey($productId), 0, $ttl);
        Cache::increment($this->countKey($productId));

        // Register the product for the next flush once per flush cycle.
        if (Cache::add($this->listedKey($productId), 1, $ttl)) {
            $registered = $this->withLock(function () use ($productId): bool {
                $pending = (array) Cache::get(self::PENDING_KEY, []);
                $pending[$productId] = true;
                Cache::forever(self::PENDING_KEY, $pending);

                return true;
            });

            if ($registered !== true) {
                Cache::forget($this->listedKey($productId));
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    protected function withLock(callable $callback): mixed
    {
        try {
            return Cache::lock(self::LOCK_KEY, 10)->block(3, $callback);
        } catch (LockTimeoutException) {
            return null;
        }
    }

    protected function countKey(int $productId): string
    {
        return 'storefront.views.count.'.$productId;
    }

    protected function listedKey(int $productId): string
    {
        return 'storefront.views.listed.'.$productId;
    }
}
