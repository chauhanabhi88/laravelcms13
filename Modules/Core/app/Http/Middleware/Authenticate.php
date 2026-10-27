<?php

namespace Modules\Core\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * The guards of the route that rejected the request.
     *
     * @var array<int, string>
     */
    protected $rejectedGuards = [];

    /**
     * Remember the route's guards so redirectTo() can pick the matching login page.
     *
     * @param  Request  $request
     * @param  array<int, string>  $guards
     * @return void
     */
    protected function unauthenticated($request, array $guards)
    {
        $this->rejectedGuards = $guards;

        parent::unauthenticated($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Supplier panel first: a signed-in customer opening it still needs the seller login.
        if (! $request->expectsJson() && in_array('seller', $this->rejectedGuards, true)) {
            return sellerLoginUrl();
        } elseif (! $request->expectsJson() && $request->user('customer')) {
            return route(config('customer.redirect_route_after_login'), updateUrlParams(['type' => config('core.route_type')]));
        } elseif (! $request->expectsJson() && $request->user()) {
            return route(config('user.redirect_route_after_login'), updateUrlParams());
        } elseif (! $request->expectsJson() && in_array('web', $this->rejectedGuards, true)) {
            return route('backend_login', updateUrlParams());
        } elseif (! $request->expectsJson()) {
            return route('customer.login', updateUrlParams(['type' => config('core.route_type')]));
        }
    }
}
