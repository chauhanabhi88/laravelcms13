<?php

declare(strict_types=1);

namespace Modules\Report\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Report\Jobs\AggregateDailyProducts;
use Modules\Report\Jobs\AggregateDailySales;
use Modules\Report\Services\LiveStats;

/**
 * Hourly (Cron): writes today's rows (overwritten each hour) and takes the product page-view
 * snapshot. Reports read today live, so this mainly keeps the table complete and the view
 * deltas current.
 */
class AggregateTodayCommand extends Command
{
    protected $signature = 'report:aggregate-today';

    protected $description = 'Refresh today\'s report statistics and the product page-view snapshot.';

    public function handle(LiveStats $live): int
    {
        $today = CarbonImmutable::today()->toDateString();

        AggregateDailySales::dispatchSync($today);
        AggregateDailyProducts::dispatchSync($today, true);
        $live->forget();

        $this->info("Aggregated {$today}.");

        return self::SUCCESS;
    }
}
