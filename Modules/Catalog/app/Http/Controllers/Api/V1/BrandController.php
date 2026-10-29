<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Repositories\CatalogBrandRepository;
use Modules\Catalog\Transformers\BrandResource;
use Modules\Core\Http\Controllers\Api\ApiController;

/**
 * Public brand lookup (enabled brands only), e.g. for the brand filter and the seller form.
 */
class BrandController extends ApiController
{
    public function __construct(protected CatalogBrandRepository $brands) {}

    public function index(Request $request): JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        $brands = $this->brands->searchEnabled($term !== '' ? $term : null, (int) config('catalog.api.brand_limit', 50));

        return $this->ok(BrandResource::collection($brands)->resolve($request));
    }
}
