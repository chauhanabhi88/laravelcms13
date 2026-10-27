<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Storefront\DTO\DeliveryEstimate;

/**
 * Serviceability, COD and estimated delivery date for one product (or variant) to one
 * pincode (FR-PDP-004). The Storefront binds the pincode-matrix estimator; the Logistics
 * module (M13) rebinds it to live courier serviceability.
 */
interface DeliveryEstimator
{
    public function estimate(Product $product, ?ProductVariant $variant, string $pincode): DeliveryEstimate;
}
