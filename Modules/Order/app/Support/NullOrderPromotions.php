<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Contracts\OrderPromotions;
use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\Models\SalesOrder;

/**
 * No Promotion module: nothing to record when an order is placed.
 */
class NullOrderPromotions implements OrderPromotions
{
    public function onPlacing(SalesOrder $order, CheckoutSummary $summary): void {}
}
