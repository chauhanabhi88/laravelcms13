<?php

declare(strict_types=1);

namespace Modules\Order\Exceptions;

use RuntimeException;

/**
 * Checkout cannot go on. $reasonCode is stable for the API; $step is the checkout page that
 * lets the buyer fix it (cart, address, payment, summary).
 */
class CheckoutException extends RuntimeException
{
    public const STEP_CART = 'cart';

    public const STEP_ADDRESS = 'address';

    public const STEP_PAYMENT = 'payment';

    public const STEP_SUMMARY = 'summary';

    public function __construct(
        string $message,
        public readonly string $reasonCode = 'checkout_failed',
        public readonly string $step = self::STEP_CART,
    ) {
        parent::__construct($message);
    }

    public static function cartNotReady(): self
    {
        return new self(trans('order::front.errors.cart_not_ready'), 'cart_not_ready', self::STEP_CART);
    }

    public static function addressRequired(): self
    {
        return new self(trans('order::front.errors.address_required'), 'address_required', self::STEP_ADDRESS);
    }

    public static function unserviceable(): self
    {
        return new self(trans('order::front.errors.unserviceable'), 'unserviceable', self::STEP_ADDRESS);
    }

    public static function paymentRequired(): self
    {
        return new self(trans('order::front.errors.payment_required'), 'payment_required', self::STEP_PAYMENT);
    }

    public static function outOfStock(): self
    {
        return new self(trans('order::front.errors.out_of_stock'), 'out_of_stock', self::STEP_CART);
    }
}
