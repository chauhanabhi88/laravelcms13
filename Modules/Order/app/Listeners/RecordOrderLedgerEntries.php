<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Hands each confirmed seller order to the LedgerPoster (a no-op until Finance, M17).
 */
class RecordOrderLedgerEntries
{
    public function __construct(private readonly LedgerPoster $ledger) {}

    public function handle(OrderConfirmed $event): void
    {
        foreach ($event->order->loadMissing('sellerOrders')->sellerOrders as $sellerOrder) {
            /** @var SellerOrder $sellerOrder */
            if ($sellerOrder->status !== SellerOrder::STATUS_PENDING_ACCEPTANCE) {
                continue;
            }

            try {
                $this->ledger->postSale($sellerOrder);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
