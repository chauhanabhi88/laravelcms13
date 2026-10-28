<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Customer\Models\Customer;

/**
 * Whether a customer has received an order before (new buyers get a COD OTP).
 * The default reads collected COD payments; the Order module (M12/M13) can rebind it.
 */
interface CustomerOrderHistory
{
    public function hasDeliveredOrders(Customer $customer): bool;
}
