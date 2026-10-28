<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Services\PickupBatchService;
use Throwable;

/**
 * A batched shipment's courier scan moves its pickup batch to picked_up / partially_picked.
 */
class UpdatePickupBatchOnShipmentStatus
{
    public function __construct(private readonly PickupBatchService $batches) {}

    public function handle(ShipmentStatusChanged $event): void
    {
        if ($event->shipment->pickup_batch_id === null) {
            return;
        }

        try {
            $batch = PickupBatch::query()->find($event->shipment->pickup_batch_id);

            if ($batch !== null) {
                $this->batches->syncStatus($batch);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
