<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CodRiskService;

/**
 * A seller order going back to origin counts against the customer's COD risk (FR-ORD-009).
 */
class RecordRtoForCodRisk
{
    public function __construct(private readonly CodRiskService $risk) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_RTO_INITIATED) {
            return;
        }

        $customer = $event->sellerOrder->salesOrder()->with('customer')->first()?->customer;

        if ($customer !== null) {
            $this->risk->recordRto($customer);
        }
    }
}
