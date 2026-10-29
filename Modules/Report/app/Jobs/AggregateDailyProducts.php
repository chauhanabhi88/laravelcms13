<?php

declare(strict_types=1);

namespace Modules\Report\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Report\Services\ProductAggregator;

/**
 * Rebuilds one day of daily_product_stat; captureViews also takes the page-view snapshot
 * (only meaningful for today).
 */
class AggregateDailyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  string  $date  Y-m-d
     */
    public function __construct(public readonly string $date, public readonly bool $captureViews = false) {}

    public function handle(ProductAggregator $aggregator): int
    {
        return $aggregator->aggregate(CarbonImmutable::parse($this->date), $this->captureViews);
    }
}
