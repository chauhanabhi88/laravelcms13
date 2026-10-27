<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Storefront\Http\Requests\DeliveryCheckRequest;
use Modules\Storefront\Services\DeliveryCheck;
use Modules\Storefront\Support\DeliveryPincode;

/**
 * POST /delivery-check (FR-PDP-004): serviceability, COD and the estimated delivery date for
 * the product page's pincode checker. The pincode is remembered in the session.
 */
class DeliveryCheckController extends Controller
{
    public function __construct(
        protected DeliveryCheck $deliveryCheck,
        protected DeliveryPincode $pincodes,
    ) {}

    public function check(DeliveryCheckRequest $request): JsonResponse
    {
        $pincode = (string) $request->validated('pincode');
        $variantId = $request->validated('variant_id');

        $estimate = $this->deliveryCheck->run((int) $request->validated('product_id'), $variantId !== null ? (int) $variantId : null, $pincode);

        if ($estimate === null) {
            return response()->json(['message' => trans('storefront::product.delivery.product_unavailable')], 404);
        }

        $this->pincodes->remember($request, $pincode);

        return response()->json($estimate->toArray());
    }
}
