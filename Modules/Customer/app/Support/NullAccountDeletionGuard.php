<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;

/**
 * No orders or returns exist before M12/M15, so nothing holds a deletion back.
 */
class NullAccountDeletionGuard implements AccountDeletionGuard
{
    public function blockingReason(Customer $customer): ?string
    {
        return null;
    }
}
