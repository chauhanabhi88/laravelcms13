<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Models\SalesOrder;

/**
 * Promotions taking part in place-order (M18). onPlacing() runs inside the place-order
 * transaction once the order and its items are written: it re-checks coupon usage limits and
 * flash-deal caps under row locks and records them. Throwing rolls the whole order back.
 * Bound to NullOrderPromotions (bindIf) until the Promotion module binds its own.
 */
interface OrderPromotions
{
    /**
     * @throws CheckoutException when a coupon limit or a deal cap was reached meanwhile
     */
    public function onPlacing(SalesOrder $order, CheckoutSummary $summary): void;
}
