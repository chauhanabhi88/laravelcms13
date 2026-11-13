<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Modules\Core\Support\Security\ContentSecurityPolicy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secure response headers on every response (plan M21 S1/S2): HSTS (HTTPS only), nosniff,
 * Referrer-Policy, Permissions-Policy, X-Frame-Options and, on HTML pages, the CSP with a
 * per-request nonce that @vite and @cspNonce print on script tags.
 *
 * Registered globally in bootstrap/app.php; headers a controller already set are kept.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The route is not matched yet, so the nonce is minted for every page; whether the
        // CSP header is sent (it depends on the route name) is decided on the way out.
        if (config('core.security.headers', true) && ContentSecurityPolicy::mode() !== ContentSecurityPolicy::MODE_OFF) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        if (! config('core.security.headers', true)) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', (string) config('core.security.frame_options', 'SAMEORIGIN'), false);
        $headers->set('Referrer-Policy', (string) config('core.security.referrer_policy', 'strict-origin-when-cross-origin'), false);
        $headers->set('Permissions-Policy', $this->permissionsPolicy($request), false);

        if ($request->isSecure() && config('core.security.hsts.enabled', true)) {
            $headers->set('Strict-Transport-Security', $this->hsts(), false);
        }

        if ($this->isHtml($response) && ContentSecurityPolicy::appliesTo($request)) {
            $headers->set(ContentSecurityPolicy::headerName(), ContentSecurityPolicy::header(Vite::cspNonce()), false);
        }

        return $response;
    }

    protected function permissionsPolicy(Request $request): string
    {
        $features = (array) config('core.security.permissions_policy', []);

        if (isset($features['camera']) && $request->routeIs(...(array) config('core.security.camera_routes', []))) {
            $features['camera'] = '(self)';
        }

        return collect($features)->map(fn (string $allow, string $feature): string => "{$feature}={$allow}")->implode(', ');
    }

    protected function hsts(): string
    {
        return 'max-age='.(int) config('core.security.hsts.max_age', 31536000)
            .(config('core.security.hsts.include_subdomains', true) ? '; includeSubDomains' : '');
    }

    /**
     * Pages only: JSON, files and redirects need no CSP.
     */
    protected function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html');
    }
}
