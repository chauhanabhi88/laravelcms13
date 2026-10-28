<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Jobs\SnapshotSellerQualityMetrics;

/**
 * order:quality-snapshot (nightly from the Cron module). --date re-runs a past day.
 */
class SnapshotSellerQualityCommand extends Command
{
    protected $signature = 'order:quality-snapshot {--date= : Day to snapshot (Y-m-d), yesterday by default}';

    protected $description = 'Write the daily per-seller quality counts (orders, seller cancellations, late dispatches, returns, RTOs).';

    public function handle(): int
    {
        $date = $this->option('date');

        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $this->error('Use --date=YYYY-MM-DD.');

            return self::INVALID;
        }

        $this->info(sprintf('Snapshot written for %d seller(s).', (int) app()->call([new SnapshotSellerQualityMetrics($date !== null ? (string) $date : null), 'handle'])));

        return self::SUCCESS;
    }
}
