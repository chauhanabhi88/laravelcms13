<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Pages\Models\Pages;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

/*
| Plan M21 §5-6: noindex rules, structured data, meta templates, share image, consent-gated
| analytics and the grievance officer contact.
*/

/**
 * @param  array<string, string>  $values
 */
function seoThemeSettings(array $values): void
{
    $current = json_decode((string) DB::table('settings')->where('name', 'theme')->value('value'), true) ?: [];
    DB::table('settings')->updateOrInsert(
        ['name' => 'theme'],
        ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
    );
}

beforeEach(function () {
    Notification::fake();
    seoThemeSettings(['gtm_container_id' => '', 'ga4_measurement_id' => '', 'og_image_url' => '', 'grievance_officer_name' => '']);
    $this->category = CatalogCategory::factory()->create(['name' => 'Kurtis', 'slug' => 'seo-kurtis']);
    $this->product = storefrontProduct(['name' => 'Block Print Kurti'], ['price' => '399', 'mrp' => '799'], $this->category);
});

test('a listing with one filter is indexable and one with two filters is not', function () {
    $url = StorefrontUrls::category('seo-kurtis');

    $this->get($url.'?cod=1')->assertOk()->assertDontSee('<meta name="robots" content="noindex, follow">', false);
    $this->get($url.'?cod=1&rating=4')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
});

test('search results print a single noindex', function () {
    $html = $this->get(StorefrontUrls::search('kurti', ['cod' => 1, 'rating' => 4]))->assertOk()->getContent();

    expect(substr_count($html, 'name="robots"'))->toBe(1);
});

test('listings carry breadcrumb and item list structured data', function () {
    $this->get(StorefrontUrls::category('seo-kurtis'))
        ->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"url":"'.StorefrontUrls::product($this->product->slug, $this->product->id).'"', false);
});

test('a category without a meta description gets the template', function () {
    $this->get(StorefrontUrls::category('seo-kurtis'))
        ->assertOk()
        ->assertSee('<meta name="description" content="Shop Kurtis online at '.config('app.name'), false);
});

test('the home page describes the organisation and the site search', function () {
    $this->get(frontRoute('homepage'))
        ->assertOk()
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SearchAction"', false)
        ->assertSee('search_term_string', false);
});

test('pages without an image share the default image', function () {
    seoThemeSettings(['og_image_url' => 'https://cdn.example.test/share.jpg']);

    $this->get(StorefrontUrls::category('seo-kurtis'))
        ->assertSee('<meta property="og:image" content="https://cdn.example.test/share.jpg">', false);
});

test('no analytics code or consent banner without a configured id', function () {
    $this->get(StorefrontUrls::product($this->product->slug, $this->product->id))
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtm.js', false)
        ->assertDontSee('id="consent-banner"', false)
        ->assertDontSee('trackEvent(', false);
});

test('a tag manager id adds consent-gated analytics and ecommerce events', function () {
    seoThemeSettings(['gtm_container_id' => 'gtm-ab12cd3']);

    $html = $this->get(StorefrontUrls::product($this->product->slug, $this->product->id))->assertOk()->getContent();

    expect($html)
        ->toContain("analytics_storage: window.analyticsConsent() === 'granted' ? 'granted' : 'denied'")
        ->toContain('GTM-AB12CD3')
        ->toContain('id="consent-banner"')
        ->toContain("'view_item'")
        ->toContain("'add_to_cart'")
        ->toContain('mini-cart:show');
});

test('a malformed analytics id is ignored', function () {
    seoThemeSettings(['gtm_container_id' => '<script>alert(1)</script>', 'ga4_measurement_id' => 'UA-123']);

    $this->get(frontRoute('homepage'))->assertOk()->assertDontSee('id="consent-banner"', false);
});

test('listings push view_item_list when analytics is on', function () {
    seoThemeSettings(['ga4_measurement_id' => 'G-TEST12345']);

    $this->get(StorefrontUrls::category('seo-kurtis'))
        ->assertOk()
        ->assertSee('view_item_list', false)
        ->assertSee('Block Print Kurti', false)
        ->assertSee('gtag/js?id=', false);
});

test('the grievance officer appears in the footer and on the grievance page', function () {
    seoThemeSettings([
        'grievance_officer_name' => 'Asha Verma',
        'grievance_officer_designation' => 'Grievance Officer',
        'grievance_officer_email' => 'grievance@example.test',
    ]);
    Pages::create(['slug' => 'grievance-officer', 'status' => config('core.enabled'), 'title' => 'Grievance Officer', 'body' => '<p>Contact us.</p>', 'meta_title' => 'Grievance Officer']);

    $this->get(frontRoute('homepage'))->assertOk()->assertSee('Grievance officer: Asha Verma')->assertSee('grievance@example.test');

    $this->get(frontRoute('page', ['grievance-officer']))
        ->assertOk()
        ->assertSee('id="grievance-officer-heading"', false)
        ->assertSee('within 48 hours');
});

test('the grievance officer is hidden until a name and a contact are set', function () {
    seoThemeSettings(['grievance_officer_name' => 'Asha Verma', 'grievance_officer_email' => '', 'grievance_officer_phone' => '', 'grievance_officer_address' => '']);

    $this->get(frontRoute('homepage'))->assertOk()->assertDontSee('Grievance officer: Asha Verma');
});
