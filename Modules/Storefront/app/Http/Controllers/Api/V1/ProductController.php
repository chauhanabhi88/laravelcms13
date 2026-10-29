<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Product\Models\Product;
use Modules\Storefront\Transformers\ProductDetailResource;
use Modules\Storefront\ViewModels\ProductPageViewModel;

/**
 * GET /api/v1/products/{id}?variant=ID — the product page payload for apps.
 */
class ProductController extends ApiController
{
    public function show(Request $request, ProductPageViewModel $page): JsonResponse
    {
        $product = Product::query()->visibleOnStorefront()->find((int) $request->route('id'));

        if ($product === null) {
            return $this->fail(404, trans('storefront::product.unavailable.title'));
        }

        $variantId = $request->integer('variant');
        $page->load($product, $variantId > 0 ? $variantId : null);

        return $this->ok((new ProductDetailResource($page))->resolve($request));
    }
}
