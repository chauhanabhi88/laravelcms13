<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Repositories\SellerOrderDeskRepository;

/**
 * Any seller-order status change makes the supplier desk's cached tab counts stale.
 */
class FlushSellerOrderDeskCounts
{
    public function __construct(private readonly SellerOrderDeskRepository $desk) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        $this->desk->forgetCounts($event->sellerOrder->seller_id);
    }
}
