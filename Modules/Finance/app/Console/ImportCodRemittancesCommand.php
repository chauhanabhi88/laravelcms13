<?php

declare(strict_types=1);

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Modules\Finance\Jobs\ImportCodRemittances;

/**
 * finance:import-cod-remittances (daily from the Cron module).
 */
class ImportCodRemittancesCommand extends Command
{
    protected $signature = 'finance:import-cod-remittances';

    protected $description = 'Import the courier aggregator COD remittances and reconcile them with delivered COD shipments.';

    public function handle(): int
    {
        $this->info(sprintf('Imported %d remittance(s).', (int) app()->call([new ImportCodRemittances, 'handle'])));

        return self::SUCCESS;
    }
}
