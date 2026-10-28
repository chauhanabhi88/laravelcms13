<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Logistics\Services\RtoService;
use Throwable;

/**
 * A package back at the seller (plan M13 §5.7): see RtoService::onDelivered().
 */
class HandleRtoDelivered
{
    public function __construct(private readonly RtoService $rto) {}

    public function handle(ShipmentRtoDelivered $event): void
    {
        try {
            $this->rto->onDelivered($event->shipment);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
