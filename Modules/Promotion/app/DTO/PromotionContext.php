<?php

declare(strict_types=1);

namespace Modules\Promotion\DTO;

use Modules\Customer\Models\Customer;

/**
 * Who is buying, where and how: the inputs of coupon rules beyond the cart itself. A guest
 * cart has no customer (customer rules are checked again at checkout, which needs a login).
 * $paymentMethod is "prepaid" / "cod" once checkout knows it, else null.
 */
final readonly class PromotionContext
{
    public function __construct(
        public ?Customer $customer,
        public string $channel,
        public ?string $paymentMethod = null,
    ) {}
}
