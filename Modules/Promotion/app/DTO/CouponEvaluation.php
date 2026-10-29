<?php

declare(strict_types=1);

namespace Modules\Promotion\DTO;

use Modules\Core\Support\Money;
use Modules\Promotion\Models\Coupon;

/**
 * CouponValidator's answer for one coupon and one cart: valid or the first rule that failed
 * (stable $reason for the API, buyer-facing $message), and when valid the item discount, its
 * share per cart line (cart item id => Money, summing to $discount) and the delivery charge a
 * free-delivery coupon waives.
 */
final readonly class CouponEvaluation
{
    public const NOT_FOUND = 'not_found';

    public const INACTIVE = 'inactive';

    public const NOT_STARTED = 'not_started';

    public const EXPIRED = 'expired';

    public const NOT_YOURS = 'not_yours';

    public const CHANNEL = 'channel';

    public const EXHAUSTED = 'exhausted';

    public const ALREADY_USED = 'already_used';

    public const FIRST_ORDER = 'first_order';

    public const NEW_CUSTOMER = 'new_customer';

    public const SCOPE = 'scope';

    public const MIN_CART = 'min_cart';

    public const PAYMENT_METHOD = 'payment_method';

    public const EMPTY_CART = 'empty_cart';

    public const DELIVERY_FREE = 'delivery_free';

    public const LOGIN_REQUIRED = 'login_required';

    /**
     * @param  array<int, Money>  $lineShares
     */
    public function __construct(
        public Coupon $coupon,
        public bool $valid,
        public ?string $reason = null,
        public ?string $message = null,
        public ?Money $discount = null,
        public array $lineShares = [],
        public ?Money $deliveryWaived = null,
        public ?Money $eligibleSubtotal = null,
    ) {}

    /**
     * @param  array<int, Money>  $lineShares
     */
    public static function passed(Coupon $coupon, Money $discount, array $lineShares, Money $deliveryWaived, Money $eligibleSubtotal): self
    {
        return new self($coupon, true, null, null, $discount, $lineShares, $deliveryWaived, $eligibleSubtotal);
    }

    public static function failed(Coupon $coupon, string $reason, string $message, ?Money $eligibleSubtotal = null): self
    {
        return new self($coupon, false, $reason, $message, Money::zero(), [], Money::zero(), $eligibleSubtotal);
    }

    /**
     * What the buyer saves with it: item discount plus delivery waived.
     */
    public function benefit(): Money
    {
        return ($this->discount ?? Money::zero())->plus($this->deliveryWaived ?? Money::zero());
    }
}
