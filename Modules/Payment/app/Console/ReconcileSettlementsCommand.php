<?php

declare(strict_types=1);

namespace Modules\Payment\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Payment\Jobs\ReconcileRazorpaySettlements;

/**
 * Queues the daily settlement reconciliation (scheduled through the Cron module).
 */
class ReconcileSettlementsCommand extends Command
{
    protected $signature = 'payment:reconcile {date? : Settlement date (Y-m-d), default yesterday}';

    protected $description = 'Reconcile one day of Razorpay settlements against captured payments and refunds';

    public function handle(): int
    {
        $date = $this->argument('date');

        if ($date !== null && Carbon::canBeCreatedFromFormat((string) $date, 'Y-m-d') === false) {
            $this->error('The date must be in Y-m-d format.');

            return self::INVALID;
        }

        ReconcileRazorpaySettlements::dispatch($date === null ? null : (string) $date);

        $this->info('Settlement reconciliation queued.');

        return self::SUCCESS;
    }
}
