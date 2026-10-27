<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Storefront\Services\RecentlyViewedService;

/**
 * The "Recently viewed" row (FR-DSC-010) as an HTML fragment: a signed-in customer's history,
 * or the ids a guest's browser keeps in localStorage (?ids=3,9,4).
 */
class RecentlyViewedController extends Controller
{
    public function __construct(protected RecentlyViewedService $recentlyViewed) {}

    public function index(Request $request): JsonResponse
    {
        $customerId = Auth::guard('customer')->id();
        $ids = $customerId !== null
            ? $this->recentlyViewed->ids((int) $customerId, (int) config('storefront.recent_views_guest_limit', 20))
            : array_map('intval', explode(',', (string) $request->query('ids', '')));

        $cards = $this->recentlyViewed->cards($ids);

        return response()->json([
            'count' => count($cards),
            'html' => $cards === [] ? '' : view('storefront::frontend.partials.product-grid-page', ['cards' => $cards])->render(),
        ]);
    }
}
