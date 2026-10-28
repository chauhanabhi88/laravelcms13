<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Jobs\AutoConfirmReturnReceipts;

/**
 * order:auto-confirm-receipts (daily from the Cron module). Runs inline.
 */
class AutoConfirmReturnReceiptsCommand extends Command
{
    protected $signature = 'order:auto-confirm-receipts';

    protected $description = 'Confirm RTO packages not confirmed by the supplier in time as received in good condition.';

    public function handle(): int
    {
        $this->info(sprintf('Confirmed %d receipt(s).', (int) app()->call([new AutoConfirmReturnReceipts, 'handle'])));

        return self::SUCCESS;
    }
}
