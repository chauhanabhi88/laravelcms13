<?php

declare(strict_types=1);

namespace Modules\Customer\Contracts;

use Modules\Customer\Models\Customer;

/**
 * Says whether a customer's account may be anonymised now. Orders (M12) and returns (M15)
 * rebind it to hold deletions while an order or return is still open; the default allows all.
 */
interface AccountDeletionGuard
{
    /**
     * A user-safe reason the deletion must wait, or null when it may proceed.
     */
    public function blockingReason(Customer $customer): ?string;
}
