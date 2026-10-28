<?php

declare(strict_types=1);

namespace Modules\Logistics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;

/**
 * Books one shipment with its courier in the background (M14 bulk accept). A refused or
 * failed booking is already flagged on the shipment for Logistics Ops, so it is not retried.
 */
class CreateProviderShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $shipmentId) {}

    public function handle(ShipmentService $shipments): void
    {
        $shipment = Shipment::query()->find($this->shipmentId);

        if ($shipment === null || $shipment->status !== Shipment::STATUS_CREATED || $shipment->isBooked()) {
            return;
        }

        try {
            $shipments->book($shipment);
        } catch (CourierException|ShipmentException $e) {
            report($e);
        }
    }
}
