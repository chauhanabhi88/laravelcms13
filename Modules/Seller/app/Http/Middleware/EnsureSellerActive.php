<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Seller\Support\CurrentSeller;
use Symfony\Component\HttpFoundation\Response;

/**
 * "seller.active": the default middleware of every supplier panel route (MapsSellerRoutes).
 *
 * Applies "seller.onboarded" first, then lets only active sellers change anything:
 * a suspended seller can still read the panel, but every write request is a 403.
 */
class EnsureSellerActive
{
    public function __construct(
        private readonly CurrentSeller $current,
        private readonly EnsureSellerOnboarded $onboarded,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->onboarded->handle($request, function (Request $request) use ($next): Response {
            $seller = $this->current->seller();

            if ($seller !== null && ! $seller->isActive() && ! $request->isMethodSafe()) {
                $message = trans('seller::panel.messages.read_only', ['reason' => $seller->status->label()]);

                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message, 'code' => '403'], 403);
                }

                abort(403, $message);
            }

            return $next($request);
        });
    }
}
