<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Seller\Support\CurrentSeller;
use Symfony\Component\HttpFoundation\Response;

/**
 * "seller.can:<role>[,<role>…]": staff permission by role. Owners pass every check;
 * "seller.can:owner" admits owners only.
 */
class SellerCan
{
    public function __construct(private readonly CurrentSeller $current) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $this->current->user();

        if ($user === null || ! $user->canAccessArea(...$roles)) {
            abort(403, trans('seller::panel.messages.no_access'));
        }

        return $next($request);
    }
}
