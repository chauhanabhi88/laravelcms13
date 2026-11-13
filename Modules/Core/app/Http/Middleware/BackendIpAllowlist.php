<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the admin backend to config('core.security.backend_ip_allowlist') (IPs or CIDRs)
 * when the list is set (plan M21 S10). An empty list (the default) allows everyone.
 *
 * Behind a load balancer, configure trusted proxies so $request->ip() is the client address.
 */
class BackendIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_values(array_filter((array) config('core.security.backend_ip_allowlist', [])));

        if ($allowed === [] || ! isAdminRequest($request) || IpUtils::checkIp((string) $request->ip(), $allowed)) {
            return $next($request);
        }

        abort(403, trans('user::user.messages.ip_not_allowed'));
    }
}
