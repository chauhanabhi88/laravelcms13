<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;
use Modules\Returns\Models\ReturnRequest;

/**
 * Holds an account deletion while a return or its refund is still in progress, after the
 * order checks of the decorated guard (M08 hook).
 */
class ReturnsAccountDeletionGuard implements AccountDeletionGuard
{
    public function __construct(private readonly AccountDeletionGuard $inner) {}

    public function blockingReason(Customer $customer): ?string
    {
        $reason = $this->inner->blockingReason($customer);

        if ($reason !== null) {
            return $reason;
        }

        return ReturnRequest::query()->forCustomer($customer->id)->open()->exists()
            ? trans('returns::front.errors.deletion_blocked')
            : null;
    }
}
