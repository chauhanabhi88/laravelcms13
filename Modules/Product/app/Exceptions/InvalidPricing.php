<?php

declare(strict_types=1);

namespace Modules\Product\Exceptions;

/**
 * A price / MRP pair broke a pricing rule (PricingService). $field is the offending input,
 * prefixed like the request ("variants.0.price").
 */
class InvalidPricing extends ProductValidationException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct([$field => $message]);
    }
}
