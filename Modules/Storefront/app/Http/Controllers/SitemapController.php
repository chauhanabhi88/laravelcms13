<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Storefront\Services\Seo\SitemapGenerator;

/**
 * robots.txt and the XML sitemaps written by `storefront:sitemap` (plan M21 E1/E2).
 */
class SitemapController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (config('storefront.seo.robots_block_all')) {
            $lines[] = 'Disallow: /';
        } else {
            foreach ((array) config('storefront.seo.robots_allow', []) as $path) {
                $lines[] = 'Allow: '.$path;
            }
            foreach ((array) config('storefront.seo.robots_disallow', []) as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('storefront.sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function index(): Response
    {
        return $this->serve(SitemapGenerator::INDEX);
    }

    public function show(Request $request): Response
    {
        return $this->serve((string) $request->route('file'));
    }

    /**
     * A missing file means `storefront:sitemap` has not run yet; it is never built on request,
     * because a crawler hit must not trigger a full catalogue scan.
     */
    protected function serve(string $name): Response
    {
        $path = SitemapGenerator::directory().'/'.$name;
        $disk = SitemapGenerator::disk();

        // A plain 404: these routes run without the session the HTML error page needs.
        if (! SitemapGenerator::isSitemapFile($name) || ! $disk->exists($path)) {
            return response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response((string) $disk->get($path), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
