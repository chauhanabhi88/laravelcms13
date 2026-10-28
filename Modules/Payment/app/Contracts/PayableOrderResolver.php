<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

/**
 * Finds sales orders for the payment pages and API. Bound to NullPayableOrderResolver
 * (bindIf) until the Order module (M12) binds its own.
 */
interface PayableOrderResolver
{
    public function findByOrderNo(string $orderNo): ?PayableOrder;

    public function find(int $salesOrderId): ?PayableOrder;
}
