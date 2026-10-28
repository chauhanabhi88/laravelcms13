<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Modules\Cart\Models\Cart;

/**
 * Select options of the admin cart grid.
 */
final class CartOptions
{
    public const OWNER_CUSTOMER = 'customer';

    public const OWNER_GUEST = 'guest';

    public const STATE_ACTIVE = 'active';

    public const STATE_ABANDONED = 'abandoned';

    public const STATE_EMPTY = 'empty';

    /**
     * @return array<string, string>
     */
    public static function owners(): array
    {
        return [
            self::OWNER_CUSTOMER => trans('cart::cart.owners.customer'),
            self::OWNER_GUEST => trans('cart::cart.owners.guest'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            Cart::TYPE_STANDARD => trans('cart::cart.types.standard'),
            Cart::TYPE_BUY_NOW => trans('cart::cart.types.buy_now'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function states(): array
    {
        return [
            self::STATE_ACTIVE => trans('cart::cart.states.active'),
            self::STATE_ABANDONED => trans('cart::cart.states.abandoned'),
            self::STATE_EMPTY => trans('cart::cart.states.empty'),
        ];
    }

    /**
     * active (changed within the abandoned window), abandoned (idle longer) or empty.
     */
    public static function stateOf(Cart $cart, int $activeLines): string
    {
        if ($activeLines === 0) {
            return self::STATE_EMPTY;
        }

        $abandonedBefore = now()->subHours((int) config('cart.abandoned.after_hours', 3));

        return $cart->last_activity_at !== null && $cart->last_activity_at->lessThan($abandonedBefore) ? self::STATE_ABANDONED : self::STATE_ACTIVE;
    }
}
