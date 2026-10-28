<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Modules\Customer\Models\Customer;
use Modules\Order\DTO\PackageReturnState;
use Modules\Order\Models\SalesOrder;

/**
 * What the customer's order pages need from returns (M15): per package, whether a return or
 * exchange can be started (and until when) and the returns raised so far.
 *
 * Bound to NullOrderReturns (bindIf) until the Returns module binds its own: Returns depends on
 * Order, never the other way round.
 */
interface OrderReturns
{
    /**
     * @return array<int, PackageReturnState> keyed by seller order id
     */
    public function forOrder(SalesOrder $order, Customer $customer): array;

    /**
     * The customer's returns list, or null without the Returns module.
     */
    public function listUrl(): ?string;
}
