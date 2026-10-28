<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Models\SalesOrder;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PayableOrderResolver;

/**
 * Sales orders for the Payment module's pages and API (replaces its null resolver).
 */
class OrderPayableResolver implements PayableOrderResolver
{
    public function findByOrderNo(string $orderNo): ?PayableOrder
    {
        return SalesOrder::query()->with(['customer', 'shippingAddress'])->where('order_no', $orderNo)->first();
    }

    public function find(int $salesOrderId): ?PayableOrder
    {
        return SalesOrder::query()->with(['customer', 'shippingAddress'])->find($salesOrderId);
    }
}
