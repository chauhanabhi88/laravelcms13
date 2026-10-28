<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Customer\Models\Customer;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Contracts\CustomerOrderHistory;
use Modules\Payment\Support\PaymentOrderHistory;

/**
 * A customer "has delivered orders" (no COD OTP for returning buyers) once any seller order of
 * theirs was delivered, prepaid included; the Payment module's collected-COD check still counts.
 */
class OrderCustomerHistory implements CustomerOrderHistory
{
    public function __construct(private readonly PaymentOrderHistory $payments) {}

    public function hasDeliveredOrders(Customer $customer): bool
    {
        return SellerOrder::query()
            ->whereIn('status', SellerOrder::DELIVERED_STATUSES)
            ->whereHas('salesOrder', fn ($query) => $query->where('customer_id', $customer->id))
            ->exists()
            || $this->payments->hasDeliveredOrders($customer);
    }
}
