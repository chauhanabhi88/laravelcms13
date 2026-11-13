<?php

declare(strict_types=1);

namespace Modules\Core\Support\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Queue health alerts (plan M21 §4), run every 5 minutes by `core:monitor`:
 * - any failed job on an "alert_on_failure" queue (webhooks by default) is critical;
 * - failed jobs on other queues in the last "failed_window_hours" are a warning;
 * - a queue backlog above "backlog_threshold" is critical.
 *
 * Alerts go to config('core.monitor.alert_channel') (point LOG_STACK at slack in production)
 * and repeat at most once per "repeat_minutes" each.
 */
class QueueMonitor
{
    /**
     * @return list<array{key: string, level: string, message: string}>
     */
    public function check(): array
    {
        $config = (array) config('core.monitor', []);
        $alerts = [];

        foreach ((array) ($config['queues'] ?? []) as $queue) {
            $size = $this->size((string) $queue);

            if ($size !== null && $size > (int) ($config['backlog_threshold'] ?? 1000)) {
                $alerts[] = ['key' => "backlog:{$queue}", 'level' => 'critical', 'message' => "Queue [{$queue}] backlog is {$size} jobs. Check the workers."];
            }
        }

        foreach ($this->recentFailures((int) ($config['failed_window_hours'] ?? 24)) as $queue => $count) {
            $critical = in_array($queue, (array) ($config['alert_on_failure'] ?? ['webhooks']), true);
            $alerts[] = [
                'key' => "failed:{$queue}",
                'level' => $critical ? 'critical' : 'warning',
                'message' => "{$count} failed job(s) on queue [{$queue}]. Inspect with php artisan queue:failed.",
            ];
        }

        return $alerts;
    }

    /**
     * Logs the alerts that were not already raised within the repeat window.
     *
     * @param  list<array{key: string, level: string, message: string}>  $alerts
     * @return int alerts sent
     */
    public function notify(array $alerts): int
    {
        $repeat = max(1, (int) config('core.monitor.repeat_minutes', 60));
        $sent = 0;

        foreach ($alerts as $alert) {
            if (! Cache::add('core-monitor:'.$alert['key'], true, now()->addMinutes($repeat))) {
                continue;
            }

            Log::channel((string) config('core.monitor.alert_channel', 'stack'))->{$alert['level']}('[ops alert] '.$alert['message'], ['alert' => $alert['key']]);
            $sent++;
        }

        return $sent;
    }

    protected function size(string $queue): ?int
    {
        try {
            return Queue::size($queue);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int> queue => failed jobs in the window
     */
    protected function recentFailures(int $hours): array
    {
        try {
            return DB::table((string) config('queue.failed.table', 'failed_jobs'))
                ->where('failed_at', '>=', now()->subHours(max(1, $hours)))
                ->groupBy('queue')
                ->selectRaw('queue, count(*) as total')
                ->pluck('total', 'queue')
                ->map(fn ($total): int => (int) $total)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
