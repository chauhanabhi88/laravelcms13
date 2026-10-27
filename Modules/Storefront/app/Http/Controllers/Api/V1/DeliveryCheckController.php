<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Storefront\Http\Requests\DeliveryCheckRequest;
use Modules\Storefront\Services\DeliveryCheck;

/**
 * POST /api/v1/delivery-check {product_id, variant_id?, pincode} — serviceability, COD and
 * the estimated delivery date for apps (stateless: the app keeps the pincode).
 */
class DeliveryCheckController extends Controller
{
    public function __construct(protected DeliveryCheck $deliveryCheck) {}

    public function check(DeliveryCheckRequest $request): JsonResponse
    {
        $variantId = $request->validated('variant_id');
        $estimate = $this->deliveryCheck->run((int) $request->validated('product_id'), $variantId !== null ? (int) $variantId : null, (string) $request->validated('pincode'));

        if ($estimate === null) {
            return response()->json([
                'success' => false,
                'message' => trans('storefront::product.delivery.product_unavailable'),
                'code' => '404',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $estimate->toArray(),
        ]);
    }
}
