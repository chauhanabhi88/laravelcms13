<?php

declare(strict_types=1);

namespace Modules\Order\Exceptions;

/**
 * A price (or the order total) changed between the summary and Place Order. Nothing was
 * written; the buyer goes back to the summary to see the new prices.
 */
class PriceChanged extends CheckoutException
{
    public function __construct()
    {
        parent::__construct(trans('order::front.errors.price_changed'), 'price_changed', self::STEP_SUMMARY);
    }
}
