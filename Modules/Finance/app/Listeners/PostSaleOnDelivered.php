<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Seller order delivered (M13): sale, commission, fees, TCS and TDS entries (plan M17 §5.2).
 * A return that ends with the goods still with the customer moves return_requested →
 * delivered again; that is not a new delivery (and the keys make a replay a no-op anyway).
 */
class PostSaleOnDelivered
{
    public function __construct(private readonly LedgerPosterImpl $ledger) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_DELIVERED || $event->from === SellerOrder::STATUS_RETURN_REQUESTED) {
            return;
        }

        try {
            $this->ledger->postDelivered(SellerOrder::query()->findOrFail($event->sellerOrder->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
