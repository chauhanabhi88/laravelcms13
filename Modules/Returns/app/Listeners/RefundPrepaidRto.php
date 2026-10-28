<?php

declare(strict_types=1);

namespace Modules\Returns\Listeners;

use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Returns\Services\RtoRefundService;
use Throwable;

/**
 * A prepaid package back at the seller is refunded automatically (plan M15 §5.7). A refund
 * failure stays with Finance (refunds screen); tracking is never interrupted.
 */
class RefundPrepaidRto
{
    public function __construct(private readonly RtoRefundService $rto) {}

    public function handle(ShipmentRtoDelivered $event): void
    {
        try {
            $this->rto->refund($event->shipment);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
