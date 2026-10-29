<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Throwable;

/**
 * An undelivered package is back at the seller (RTO, M13): RTO freight when the seller pays
 * it (default: the platform absorbs it). Forward and reverse freight are posted with the
 * delivery and the return refund.
 */
class PostLogisticsCharges
{
    public function __construct(private readonly LedgerPosterImpl $ledger) {}

    public function handle(ShipmentRtoDelivered $event): void
    {
        try {
            $this->ledger->postRto($event->shipment);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
