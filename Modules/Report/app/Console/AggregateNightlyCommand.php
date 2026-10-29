<?php

declare(strict_types=1);

namespace Modules\Report\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Report\Jobs\AggregateDailyProducts;
use Modules\Report\Jobs\AggregateDailySales;
use Modules\Report\Services\ReportExporter;
use Throwable;

/**
 * Nightly (Cron, 02:00): aggregates D-1 and re-aggregates the days before it, back to
 * report.reaggregate_days, for the late-changing metrics (RTO, returns, cancellations, fees).
 * A failing day is reported and the others still run. Also prunes expired exports.
 */
class AggregateNightlyCommand extends Command
{
    protected $signature = 'report:aggregate-nightly';

    protected $description = 'Aggregate yesterday\'s report statistics and refresh the previous days.';

    public function handle(ReportExporter $exporter): int
    {
        $days = max(1, (int) config('report.reaggregate_days', 30));
        $failed = 0;

        for ($offset = 1; $offset <= $days; $offset++) {
            $day = CarbonImmutable::today()->subDays($offset)->toDateString();

            try {
                AggregateDailySales::dispatchSync($day);
                AggregateDailyProducts::dispatchSync($day);
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $this->error("{$day}: {$e->getMessage()}");
            }
        }

        $pruned = $exporter->prune();
        $this->info("Aggregated {$days} day(s), {$failed} failed; pruned {$pruned} export(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
