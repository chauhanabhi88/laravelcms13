<?php

declare(strict_types=1);

namespace Modules\Returns\Listeners;

use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Returns\Services\ReturnService;
use Throwable;

/**
 * Reverse shipment scans drive the return (plan M15 §5.5): picked up, delivered to the
 * seller, cancelled before pickup, lost. A failure here never breaks tracking.
 */
class AdvanceReturnOnReverseShipmentStatus
{
    public function __construct(private readonly ReturnService $returns) {}

    public function handle(ShipmentStatusChanged $event): void
    {
        try {
            $this->returns->onReverseShipmentStatus($event->shipment, $event->from, $event->to);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
