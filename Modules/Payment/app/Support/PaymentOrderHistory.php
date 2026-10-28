<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\CustomerOrderHistory;
use Modules\Payment\Models\Payment;

/**
 * Default CustomerOrderHistory: a customer "has delivered orders" once a COD payment of
 * theirs was collected (the courier delivered it). The Order module may rebind this with a
 * check on delivered seller orders, which also covers prepaid deliveries.
 */
class PaymentOrderHistory implements CustomerOrderHistory
{
    public function hasDeliveredOrders(Customer $customer): bool
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Payment::STATUS_COLLECTED, Payment::STATUS_REMITTED])
            ->exists();
    }
}
