<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Support\Ops\QueueMonitor;

/**
 * Raises ops alerts for failed jobs and queue backlogs (cron every 5 minutes, plan M21 §4).
 */
class MonitorCommand extends Command
{
    protected $signature = 'core:monitor';

    protected $description = 'Alert on failed jobs (webhooks first) and queue backlogs';

    public function handle(QueueMonitor $monitor): int
    {
        $alerts = $monitor->check();

        foreach ($alerts as $alert) {
            $this->line(strtoupper($alert['level']).' '.$alert['message']);
        }

        $sent = $monitor->notify($alerts);
        $this->info(count($alerts) === 0 ? 'All queues healthy.' : "{$sent} new alert(s) sent.");

        return self::SUCCESS;
    }
}
