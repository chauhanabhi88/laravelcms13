<?php

declare(strict_types=1);

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Finance\Jobs\BuildPayoutBatch;
use Modules\Finance\Support\FinanceSettings;

/**
 * finance:build-payouts (daily from the Cron module; builds only on the payout day unless
 * --force). Runs inline.
 */
class BuildPayoutBatchCommand extends Command
{
    protected $signature = 'finance:build-payouts {--force : Build even when today is not the payout day}';

    protected $description = 'Build the draft payout batch of the settlement cycle for Finance to approve.';

    public function handle(): int
    {
        if (! $this->option('force') && strtolower(Carbon::today()->englishDayOfWeek) !== FinanceSettings::payoutDay()) {
            $this->info('Not the payout day ('.FinanceSettings::payoutDay().').');

            return self::SUCCESS;
        }

        $batch = app()->call([new BuildPayoutBatch, 'handle']);
        $this->info($batch === null ? 'No seller is payable.' : sprintf('Built %s: %d payout(s), %s.', $batch->batch_no, $batch->sellers_count, $batch->total_amount->format(true)));

        return self::SUCCESS;
    }
}
