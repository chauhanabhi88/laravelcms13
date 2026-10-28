<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\Jobs\AutoAcceptOrders;

/**
 * order:auto-accept (every 10 minutes from the Cron module). Runs inline.
 */
class AutoAcceptOrdersCommand extends Command
{
    protected $signature = 'order:auto-accept {--limit=200 : Seller orders to look at in one run}';

    protected $description = 'Accept pending seller orders of suppliers with auto-accept on, and book their shipments.';

    public function handle(): int
    {
        /** @var FulfilmentResult $result */
        $result = app()->call([new AutoAcceptOrders(max(1, (int) $this->option('limit'))), 'handle']);

        $this->info(sprintf('Accepted %d, AWB pending %d, failed %d.', $result->count(FulfilmentResult::OK), $result->count(FulfilmentResult::AWB_PENDING), $result->count(FulfilmentResult::FAILED)));

        return self::SUCCESS;
    }
}
