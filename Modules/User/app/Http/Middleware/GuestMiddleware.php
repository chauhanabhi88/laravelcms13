<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class GuestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            if($guard) {
                return redirect()->route(config("$guard.redirect_route_after_login"), updateUrlParams(['type' => config('core.route_type')]));
            } else {
                return redirect()->route(config("user.redirect_route_after_login"), updateUrlParams());
            }
        }

        return $next($request);
    }
}