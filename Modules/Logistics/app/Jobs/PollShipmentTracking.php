<?php

declare(strict_types=1);

namespace Modules\Logistics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\TrackingService;
use Throwable;

/**
 * Polling fallback for missed webhooks (plan M13 §5.4): booked API-courier shipments that are
 * not final and were not synced for poll_stale_hours are re-read, oldest sync first.
 */
class PollShipmentTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public ?int $limit = null) {}

    /**
     * @return int shipments polled
     */
    public function handle(TrackingService $tracking): int
    {
        $staleBefore = Carbon::now()->subHours(max(1, (int) config('logistics.poll_stale_hours', 4)));

        $shipments = Shipment::query()
            ->open()
            ->whereNotNull('awb')
            ->whereIn('status', array_diff(Shipment::STATUSES, [Shipment::STATUS_CREATED]))
            ->whereHas('courier', fn ($query) => $query->where('driver', '!=', Courier::DRIVER_MANUAL))
            ->where(fn ($query) => $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $staleBefore))
            ->with('courier')
            ->orderByRaw('last_synced_at is not null, last_synced_at')
            ->limit($this->limit ?? (int) config('logistics.poll_batch_size', 100))
            ->get();

        foreach ($shipments as $shipment) {
            try {
                $tracking->sync($shipment);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $shipments->count();
    }
}
