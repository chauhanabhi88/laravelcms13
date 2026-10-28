<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PayableOrderResolver;

/**
 * No orders exist until the Order module (M12) binds its resolver: every payment page 404s.
 */
class NullPayableOrderResolver implements PayableOrderResolver
{
    public function findByOrderNo(string $orderNo): ?PayableOrder
    {
        return null;
    }

    public function find(int $salesOrderId): ?PayableOrder
    {
        return null;
    }
}
