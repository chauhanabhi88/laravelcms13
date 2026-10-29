<?php

declare(strict_types=1);

namespace Modules\Report\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Report\Services\SalesAggregator;

/**
 * Rebuilds one day of daily_sales_stat (idempotent).
 */
class AggregateDailySales implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  string  $date  Y-m-d
     */
    public function __construct(public readonly string $date) {}

    public function handle(SalesAggregator $aggregator): int
    {
        return $aggregator->aggregate(CarbonImmutable::parse($this->date));
    }
}
