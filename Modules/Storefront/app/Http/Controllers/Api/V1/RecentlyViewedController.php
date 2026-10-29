<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Storefront\Services\RecentlyViewedService;
use Modules\Storefront\Transformers\ProductCardResource;

/**
 * GET /api/v1/recently-viewed?ids=3,9,4 — cards for the ids an app keeps locally (only
 * products still on the storefront, in the given order).
 */
class RecentlyViewedController extends ApiController
{
    public function __construct(protected RecentlyViewedService $recentlyViewed) {}

    public function index(Request $request): JsonResponse
    {
        $cards = $this->recentlyViewed->cards(array_map('intval', explode(',', (string) $request->query('ids', ''))));

        return $this->ok(ProductCardResource::collection($cards)->resolve($request));
    }
}
