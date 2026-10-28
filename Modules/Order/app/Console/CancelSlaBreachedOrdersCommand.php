<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Jobs\CancelSlaBreachedOrders;

/**
 * order:sla-cancel (hourly from the Cron module). Runs inline: it releases reserved stock.
 */
class CancelSlaBreachedOrdersCommand extends Command
{
    protected $signature = 'order:sla-cancel';

    protected $description = 'Cancel seller orders not picked up by their dispatch deadline plus the grace period.';

    public function handle(): int
    {
        $this->info(sprintf('Cancelled %d order(s).', (int) app()->call([new CancelSlaBreachedOrders, 'handle'])));

        return self::SUCCESS;
    }
}
