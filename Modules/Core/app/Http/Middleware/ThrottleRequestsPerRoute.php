<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * "throttle:N,M" with one bucket per route. The framework keys unnamed throttles by user or
 * IP only, so every throttled route shares a single counter: a buyer's cart, pincode and
 * notification requests (60/min) could exhaust OTP verification (10/min) or place order
 * (5/min). Scoping the key by route keeps each limit to the route that declares it. Named
 * limiters (throttle:otp, throttle:api, …) are unchanged: they build their own keys.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $scope = $route !== null ? ($route->getName() ?? $request->method().' '.$route->uri()) : '';

        return sha1($scope.'|'.parent::resolveRequestSignature($request));
    }
}
