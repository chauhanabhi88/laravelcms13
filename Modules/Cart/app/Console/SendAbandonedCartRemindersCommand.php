<?php

declare(strict_types=1);

namespace Modules\Cart\Console;

use Illuminate\Console\Command;
use Modules\Cart\Jobs\SendAbandonedCartReminders;

/**
 * Queues the abandoned-cart reminder job (scheduled hourly through the Cron module).
 */
class SendAbandonedCartRemindersCommand extends Command
{
    protected $signature = 'cart:abandoned-reminders';

    protected $description = 'Queue abandoned-cart reminders for customer carts idle between 3 and 24 hours';

    public function handle(): int
    {
        SendAbandonedCartReminders::dispatch();

        $this->info('Abandoned-cart reminders queued.');

        return self::SUCCESS;
    }
}
