<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Today's numbers, computed live from the order tables (plan M19 §1) and cached briefly
 * (report.live_cache_seconds). Cached values are plain arrays.
 */
class LiveStats
{
    public function __construct(
        private readonly SalesAggregator $sales,
        private readonly ProductAggregator $products,
    ) {}

    /**
     * @return array<string, array<string, int|string>> daily_sales_stat rows keyed by grain key
     */
    public function sales(): array
    {
        $today = CarbonImmutable::today();

        return $this->remember('sales.'.$today->toDateString(), fn (): array => $this->sales->compute($today));
    }

    /**
     * @return array<int, array{product_id: int, seller_id: int, units: int, gmv: string, returns: int}>
     */
    public function products(): array
    {
        $today = CarbonImmutable::today();

        return $this->remember('products.'.$today->toDateString(), fn (): array => $this->products->compute($today));
    }

    public function forget(): void
    {
        $today = CarbonImmutable::today()->toDateString();

        Cache::forget('report.live.sales.'.$today);
        Cache::forget('report.live.products.'.$today);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T
     */
    private function remember(string $key, callable $compute): mixed
    {
        $seconds = (int) config('report.live_cache_seconds', 300);

        return $seconds > 0 ? Cache::remember('report.live.'.$key, $seconds, $compute) : $compute();
    }
}
