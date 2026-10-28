<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Jobs\CancelAbandonedOrders;

/**
 * order:cancel-abandoned (every 10 minutes from the Cron module). Runs inline: it is short
 * and must not wait for a queue worker to release reserved stock.
 */
class CancelAbandonedOrdersCommand extends Command
{
    protected $signature = 'order:cancel-abandoned {--limit=200 : Orders to look at in one run}';

    protected $description = 'Confirm or cancel orders still waiting for a payment that has finished or expired.';

    public function handle(): int
    {
        /** @var array{confirmed: int, cancelled: int} $result */
        $result = app()->call([new CancelAbandonedOrders(max(1, (int) $this->option('limit'))), 'handle']);

        $this->info(sprintf('Confirmed %d, cancelled %d.', $result['confirmed'] ?? 0, $result['cancelled'] ?? 0));

        return self::SUCCESS;
    }
}
