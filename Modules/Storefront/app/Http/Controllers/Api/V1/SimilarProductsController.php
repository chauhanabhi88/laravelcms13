<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Product\Models\Product;
use Modules\Storefront\Services\SimilarProductsService;
use Modules\Storefront\Transformers\ProductCardResource;

/**
 * GET api/v1/products/{id}/similar?type=similar|seller: the cards of the PDP's "Similar
 * products" (same leaf category, near price) and "More from this seller" rows.
 */
class SimilarProductsController extends ApiController
{
    public function __construct(protected SimilarProductsService $similar) {}

    public function index(Request $request): JsonResponse
    {
        $product = Product::query()->visibleOnStorefront()->find((int) $request->route('id'));

        if ($product === null) {
            return $this->fail(404, trans('storefront::product.unavailable.title'));
        }

        $type = $request->query('type') === SimilarProductsService::SELLER ? SimilarProductsService::SELLER : SimilarProductsService::SIMILAR;

        return $this->ok(ProductCardResource::collection($this->similar->cards($product, $type))->resolve($request), ['type' => $type]);
    }
}
