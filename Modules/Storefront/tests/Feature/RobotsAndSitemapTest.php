<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Models\Product;
use Modules\Storefront\Services\Seo\SitemapGenerator;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    config(['storefront.seo.robots_block_all' => false]);
});

/**
 * Every <loc> of every sitemap chunk the index lists.
 *
 * @return list<string>
 */
function sitemapLocations(): array
{
    $index = Storage::disk('local')->get('sitemaps/'.SitemapGenerator::INDEX);
    preg_match_all('#<loc>([^<]+)</loc>#', (string) $index, $files);

    $locations = [];
    foreach ($files[1] as $fileUrl) {
        $chunk = Storage::disk('local')->get('sitemaps/'.basename(html_entity_decode($fileUrl)));
        preg_match_all('#<loc>([^<]+)</loc>#', (string) $chunk, $urls);
        array_push($locations, ...array_map('html_entity_decode', $urls[1]));
    }

    return $locations;
}

test('robots.txt keeps crawlers out of private pages and points to the sitemap', function () {
    $response = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Disallow: /cart')
        ->toContain('Disallow: /checkout')
        ->toContain('Disallow: /customer/')
        ->toContain('Disallow: /supplier/')
        ->toContain('Allow: /supplier$')
        ->toContain('Disallow: /backend')
        ->toContain('Disallow: /search?*&')
        ->toContain('Sitemap: '.route('storefront.sitemap'))
        ->not->toContain("Disallow: /\n");
});

test('robots.txt turns every crawler away outside production', function () {
    config(['storefront.seo.robots_block_all' => true]);

    expect($this->get('/robots.txt')->getContent())
        ->toBe("User-agent: *\nDisallow: /\n");
});

test('the sitemap lists live products only, plus categories, stores and pages', function () {
    $category = CatalogCategory::factory()->create(['slug' => 'sitemap-sarees']);
    $hidden = CatalogCategory::factory()->create(['slug' => 'sitemap-hidden', 'status' => 2]);
    $hiddenChild = CatalogCategory::factory()->child($hidden)->create(['slug' => 'sitemap-hidden-child']);
    $live = storefrontProduct(['name' => 'Sitemap Silk Saree'], [], $category);
    $draft = Product::factory()->draft()->withVariants(1)->inCategory($category)->create();
    $paused = Product::factory()->paused()->withVariants(1)->inCategory($category)->create();

    $this->artisan('storefront:sitemap')->assertSuccessful();

    $locations = sitemapLocations();

    expect($locations)
        ->toContain(frontRoute('homepage'))
        ->toContain(StorefrontUrls::category('sitemap-sarees'))
        ->toContain(StorefrontUrls::product($live->slug, $live->id))
        ->toContain(StorefrontUrls::store((string) $live->seller->store_slug))
        ->not->toContain(StorefrontUrls::product($draft->slug, $draft->id))
        ->not->toContain(StorefrontUrls::product($paused->slug, $paused->id))
        ->not->toContain(StorefrontUrls::category('sitemap-hidden'))
        ->not->toContain(StorefrontUrls::category($hiddenChild->slug));
});

test('sitemaps are split into chunks and stale chunks are removed', function () {
    config(['storefront.seo.sitemap_chunk' => 1]);
    storefrontProduct();
    storefrontProduct();
    Storage::disk('local')->put('sitemaps/sitemap-products-99.xml', '<urlset/>');

    $this->artisan('storefront:sitemap')->assertSuccessful();

    expect(Storage::disk('local')->exists('sitemaps/sitemap-products-1.xml'))->toBeTrue()
        ->and(Storage::disk('local')->exists('sitemaps/sitemap-products-2.xml'))->toBeTrue()
        ->and(Storage::disk('local')->exists('sitemaps/sitemap-products-99.xml'))->toBeFalse();
});

test('the sitemap index and chunks are served as xml', function () {
    storefrontProduct();
    $this->artisan('storefront:sitemap')->assertSuccessful();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee(route('storefront.sitemap.file', ['sitemap-products-1.xml']), false);

    $this->get(route('storefront.sitemap.file', ['sitemap-products-1.xml']))
        ->assertOk()
        ->assertSee('<urlset', false);
});

test('a sitemap that has not been generated is not found and never built on request', function () {
    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/sitemaps/sitemap-products-7.xml')->assertNotFound();

    expect(Storage::disk('local')->exists('sitemaps/'.SitemapGenerator::INDEX))->toBeFalse();
});

test('only generated sitemap names can be served', function () {
    expect(SitemapGenerator::isSitemapFile('sitemap.xml'))->toBeTrue()
        ->and(SitemapGenerator::isSitemapFile('sitemap-products-12.xml'))->toBeTrue()
        ->and(SitemapGenerator::isSitemapFile('../.env'))->toBeFalse()
        ->and(SitemapGenerator::isSitemapFile('sitemap-products-1.php'))->toBeFalse();
});
