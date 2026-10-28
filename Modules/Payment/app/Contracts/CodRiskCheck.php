<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;

/**
 * An extra COD rule (e.g. the Order module's RTO and open-COD-order limits, M12).
 * Tag implementations with CodEligibilityService::RISK_CHECK_TAG.
 */
interface CodRiskCheck
{
    /**
     * A buyer-facing reason when COD must be refused, null when this rule allows it.
     */
    public function blockReason(Customer $customer, Money $orderTotal, ?string $pincode): ?string;
}
