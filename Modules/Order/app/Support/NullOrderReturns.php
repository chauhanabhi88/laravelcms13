<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Customer\Models\Customer;
use Modules\Order\Contracts\OrderReturns;
use Modules\Order\Models\SalesOrder;

/**
 * No Returns module: the order pages offer no returns.
 */
class NullOrderReturns implements OrderReturns
{
    public function forOrder(SalesOrder $order, Customer $customer): array
    {
        return [];
    }

    public function listUrl(): ?string
    {
        return null;
    }
}
