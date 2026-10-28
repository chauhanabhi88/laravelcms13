<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;

/**
 * An account with an order still on its way cannot be anonymised yet (M08 deletion request).
 */
class OrderAccountDeletionGuard implements AccountDeletionGuard
{
    public function blockingReason(Customer $customer): ?string
    {
        $open = SalesOrder::query()
            ->forCustomer($customer->id)
            ->whereNotIn('status', SalesOrder::CLOSED_STATUSES)
            ->exists();

        return $open ? trans('order::front.account.deletion_blocked') : null;
    }
}
