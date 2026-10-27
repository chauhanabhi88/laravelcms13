<?php

declare(strict_types=1);

namespace Modules\Storefront\Services;

use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Storefront\Contracts\DeliveryEstimator;
use Modules\Storefront\DTO\DeliveryEstimate;

/**
 * Resolves the product (visible on the storefront) and optional variant of a delivery check
 * and asks the bound DeliveryEstimator. Shared by the web and API endpoints.
 */
class DeliveryCheck
{
    public function __construct(protected DeliveryEstimator $estimator) {}

    /**
     * Null when the product is not on the storefront.
     *
     * @throws ValidationException when the variant is not an enabled variant of the product
     */
    public function run(int $productId, ?int $variantId, string $pincode): ?DeliveryEstimate
    {
        $product = Product::query()->visibleOnStorefront()->find($productId);

        if ($product === null) {
            return null;
        }

        $variant = null;

        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('status', config('core.enabled'))
                ->find($variantId);

            if ($variant === null) {
                throw ValidationException::withMessages(['variant_id' => trans('storefront::product.delivery.invalid_variant')]);
            }
        }

        return $this->estimator->estimate($product, $variant, $pincode);
    }
}
