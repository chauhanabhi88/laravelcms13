<?php

declare(strict_types=1);

namespace Modules\Core\Support\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Builds the Content-Security-Policy header from config('core.security.csp') (plan M21 S2).
 *
 * Inline scripts carry the per-request Vite nonce (`<script @cspNonce>`); Alpine needs
 * 'unsafe-eval'. While the Vite dev server runs (public/hot), its origin is allowed too.
 */
final class ContentSecurityPolicy
{
    public const MODE_OFF = 'off';

    public const MODE_REPORT_ONLY = 'report-only';

    public const MODE_ENFORCE = 'enforce';

    public static function mode(): string
    {
        $mode = (string) config('core.security.csp.mode', self::MODE_REPORT_ONLY);

        return in_array($mode, [self::MODE_REPORT_ONLY, self::MODE_ENFORCE], true) ? $mode : self::MODE_OFF;
    }

    public static function headerName(): string
    {
        return self::mode() === self::MODE_ENFORCE ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
    }

    /**
     * CSP is sent on storefront and supplier panel pages, not on the admin backend (see config).
     */
    public static function appliesTo(Request $request): bool
    {
        if (self::mode() === self::MODE_OFF || isApiRequest($request)) {
            return false;
        }

        return ! (config('core.security.csp.skip_backend', true) && isAdminRequest($request));
    }

    public static function header(?string $nonce): string
    {
        $directives = (array) config('core.security.csp.directives', []);
        $cdnHosts = (array) config('core.security.csp.cdn_hosts', []);
        $devServer = self::viteDevServer();

        foreach (['script-src', 'style-src', 'img-src', 'font-src', 'connect-src'] as $name) {
            $directives[$name] = array_merge($directives[$name] ?? [], $cdnHosts);

            if ($devServer !== null) {
                $directives[$name][] = $devServer;
            }
        }

        if ($devServer !== null) {
            $directives['connect-src'][] = preg_replace('#^http#', 'ws', $devServer);
        }

        if ($nonce !== null && $nonce !== '') {
            $directives['script-src'][] = "'nonce-{$nonce}'";
        }

        if (($reportUri = self::reportUri()) !== null) {
            $directives['report-uri'] = [$reportUri];
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', array_unique($sources))))
            ->implode('; ');
    }

    protected static function reportUri(): ?string
    {
        // Core front routes never carry the {locale} prefix.
        return Route::has('core.csp_report') ? route('core.csp_report') : null;
    }

    /**
     * The Vite dev server origin from public/hot, outside production only.
     */
    protected static function viteDevServer(): ?string
    {
        $hotFile = public_path('hot');

        if (app()->isProduction() || ! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        return preg_match('#^https?://[^/\s]+#', $url, $match) ? $match[0] : null;
    }
}
