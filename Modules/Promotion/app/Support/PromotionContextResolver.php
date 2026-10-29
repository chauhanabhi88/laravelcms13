<?php

declare(strict_types=1);

namespace Modules\Promotion\Support;

use Modules\Cart\DTO\CartSnapshot;
use Modules\Customer\Models\Customer;
use Modules\Promotion\DTO\PromotionContext;
use Modules\Promotion\Models\Coupon;

/**
 * Builds the PromotionContext of a cart: its customer (customer carts only) and the channel of
 * the current request ("app" for API clients, "web" otherwise).
 */
class PromotionContextResolver
{
    public function for(CartSnapshot $snapshot, ?string $paymentMethod = null): PromotionContext
    {
        $customer = $snapshot->customerId !== null ? Customer::query()->find($snapshot->customerId) : null;

        return new PromotionContext($customer, self::channel(), $paymentMethod);
    }

    public static function channel(): string
    {
        $request = app()->bound('request') ? request() : null;

        if ($request !== null && ($request->is('api/*') || $request->is('*/api/*'))) {
            return Coupon::CHANNEL_APP;
        }

        return Coupon::CHANNEL_WEB;
    }
}
