<?php

namespace Modules\Core\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson() && $request->user("customer")) {
            return route("customer.redirect_route_after_login",updateUrlParams(['type' => config('core.route_type')]));
        } else if (! $request->expectsJson() && $request->user()) {
            return route(config("user.redirect_route_after_login"),updateUrlParams());
        } else if (! $request->expectsJson()) {
            return route("customer.login",updateUrlParams(['type' => config('core.route_type')]));
        }
    }
}
