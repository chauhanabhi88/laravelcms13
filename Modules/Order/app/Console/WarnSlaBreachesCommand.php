<?php

declare(strict_types=1);

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Jobs\WarnSlaBreaches;

/**
 * order:sla-warn (hourly from the Cron module). Runs inline.
 */
class WarnSlaBreachesCommand extends Command
{
    protected $signature = 'order:sla-warn';

    protected $description = 'Warn suppliers once about orders not yet packed with 75% of their dispatch SLA used.';

    public function handle(): int
    {
        $this->info(sprintf('Warned %d order(s).', (int) app()->call([new WarnSlaBreaches, 'handle'])));

        return self::SUCCESS;
    }
}
