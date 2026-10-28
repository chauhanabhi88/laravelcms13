<?php

declare(strict_types=1);

namespace Modules\Logistics\Console;

use Illuminate\Console\Command;
use Modules\Logistics\Jobs\PollShipmentTracking;
use Modules\Logistics\Services\TrackingService;

/**
 * logistics:poll-tracking — scheduled through the Cron module (every 2 hours).
 */
class PollTrackingCommand extends Command
{
    protected $signature = 'logistics:poll-tracking {--limit= : Shipments per run}';

    protected $description = 'Poll courier tracking for shipments not synced recently (webhook fallback)';

    public function handle(TrackingService $tracking): int
    {
        $limit = $this->option('limit');
        $count = (new PollShipmentTracking(is_numeric($limit) ? (int) $limit : null))->handle($tracking);

        $this->info("Polled {$count} shipment(s).");

        return self::SUCCESS;
    }
}
