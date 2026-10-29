<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Customer\Models\Customer;
use Modules\Order\Contracts\OrderReviews;
use Modules\Order\Models\SalesOrder;

/**
 * No Review module: the order pages offer no ratings.
 */
class NullOrderReviews implements OrderReviews
{
    public function forOrder(SalesOrder $order, Customer $customer): array
    {
        return [];
    }
}
