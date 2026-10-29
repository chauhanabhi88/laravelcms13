<?php

declare(strict_types=1);

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Modules\Finance\Jobs\GenerateMonthlyFeeInvoices;

/**
 * finance:fee-invoices [YYYY-MM] (monthly from the Cron module, default: last month).
 */
class GenerateFeeInvoicesCommand extends Command
{
    protected $signature = 'finance:fee-invoices {period? : Month as YYYY-MM (default: last month)}';

    protected $description = 'Issue the platform fee invoices (commission and fees + GST) to sellers for a month.';

    public function handle(): int
    {
        $period = $this->argument('period');

        if ($period !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period)) {
            $this->error('The period must look like 2026-11.');

            return self::FAILURE;
        }

        $count = app()->call([new GenerateMonthlyFeeInvoices($period), 'handle']);
        $this->info(sprintf('Issued %d fee invoice(s).', $count));

        return self::SUCCESS;
    }
}
