<?php

declare(strict_types=1);

namespace Modules\Order\Policies;

use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;

/**
 * Storefront and API access to orders: a customer sees and cancels only their own. Denials
 * are turned into 404s by the controllers, so order numbers cannot be probed.
 */
class SalesOrderPolicy
{
    public function view(Customer $customer, SalesOrder $order): bool
    {
        return $order->customer_id === $customer->id;
    }

    public function cancel(Customer $customer, SalesOrder $order): bool
    {
        return $this->view($customer, $order);
    }
}
