<?php

declare(strict_types=1);

namespace Modules\Report\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Report\Jobs\AggregateDailyProducts;
use Modules\Report\Jobs\AggregateDailySales;
use Modules\Report\Services\LiveStats;
use Throwable;

/**
 * Rebuilds the report aggregates for a range of days (plan M19 §4). Without options it starts
 * at the first placed order and ends yesterday. Today is rebuilt with a page-view snapshot.
 */
class RebuildStatsCommand extends Command
{
    protected $signature = 'report:rebuild {--from= : First day (Y-m-d), default the first placed order} {--to= : Last day (Y-m-d), default yesterday}';

    protected $description = 'Rebuild the daily sales and product statistics for a range of days.';

    public function handle(LiveStats $live): int
    {
        try {
            $to = $this->option('to') ? CarbonImmutable::parse((string) $this->option('to'))->startOfDay() : CarbonImmutable::yesterday();
            $first = DB::table('sales_order')->whereNotNull('placed_at')->min('placed_at');
            $from = $this->option('from')
                ? CarbonImmutable::parse((string) $this->option('from'))->startOfDay()
                : ($first !== null ? CarbonImmutable::parse($first)->startOfDay() : $to);
        } catch (Throwable) {
            $this->error('Dates must be Y-m-d.');

            return self::FAILURE;
        }

        $today = CarbonImmutable::today();
        $to = $to->greaterThan($today) ? $today : $to;

        if ($from->greaterThan($to)) {
            $this->error('--from must not be after --to.');

            return self::FAILURE;
        }

        $days = (int) $from->diffInDays($to) + 1;
        $this->info("Rebuilding {$days} day(s): {$from->toDateString()} to {$to->toDateString()}.");
        $bar = $this->output->createProgressBar($days);

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            AggregateDailySales::dispatchSync($day->toDateString());
            AggregateDailyProducts::dispatchSync($day->toDateString(), $day->equalTo($today));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $live->forget();

        return self::SUCCESS;
    }
}
