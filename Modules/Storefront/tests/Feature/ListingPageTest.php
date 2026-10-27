<?php

use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Models\SearchTermStat;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    $this->root = CatalogCategory::factory()->create(['name' => 'Women']);
    $this->leaf = CatalogCategory::factory()->child($this->root)->create(['name' => 'Kurtis', 'slug' => 'women-kurtis']);
});

test('a category page lists the products of its subtree with filters and a canonical tag', function () {
    $product = storefrontProduct(['name' => 'Printed Cotton Kurti'], ['price' => '349', 'mrp' => '699'], $this->leaf);

    $this->get(frontRoute('storefront.category', [$this->root->slug]))
        ->assertSuccessful()
        ->assertViewIs('storefront::frontend.listing')
        ->assertSee('Printed Cotton Kurti')
        ->assertSee('₹349')
        ->assertSee('data-product-id="'.$product->id.'"', false)
        ->assertSee('<link rel="canonical" href="'.frontRoute('storefront.category', [$this->root->slug]).'">', false)
        ->assertSee('data-listing-filters', false)
        ->assertSee('Kurtis');
});

test('the canonical keeps filters but drops sort and page', function () {
    storefrontProduct([], [], $this->leaf);

    $this->get(frontRoute('storefront.category', [$this->leaf->slug]).'?cod=1&sort=price_asc')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.frontRoute('storefront.category', [$this->leaf->slug]).'?cod=1">', false);
});

test('unknown, disabled and hidden-by-parent categories are not found', function () {
    $disabled = CatalogCategory::factory()->create(['status' => 2]);
    $this->root->update(['status' => 2]);

    $this->get(frontRoute('storefront.category', ['no-such-category']))->assertNotFound()->assertViewIs('pages::errors.front.error');
    $this->get(frontRoute('storefront.category', [$disabled->slug]))->assertNotFound();
    $this->get(frontRoute('storefront.category', [$this->leaf->slug]))->assertNotFound();
});

test('infinite scroll pages come back as JSON with the next page URL', function () {
    config(['storefront.per_page' => 2]);
    foreach (range(1, 3) as $index) {
        storefrontProduct(['name' => 'Kurti '.$index], [], $this->leaf);
    }

    $first = $this->get(frontRoute('storefront.category', [$this->leaf->slug]))->assertSuccessful();
    $first->assertSee('rel="next"', false);

    $this->getJson(frontRoute('storefront.category', [$this->leaf->slug]).'?page=2&partial=grid')
        ->assertSuccessful()
        ->assertJsonPath('next_page_url', null)
        ->assertJsonStructure(['html', 'next_page_url', 'url'])
        ->assertJson(fn ($json) => $json->where('html', fn (string $html): bool => substr_count($html, 'data-product-id=') === 1)->etc());

    $this->get(frontRoute('storefront.category', [$this->leaf->slug]).'?page=2')->assertSuccessful()->assertSee('rel="prev"', false);
    $this->get(frontRoute('storefront.category', [$this->leaf->slug]).'?page=9')->assertNotFound();
});

test('filter changes return the results and facets as JSON', function () {
    storefrontProduct(['name' => 'Cheap Kurti'], ['price' => '149', 'mrp' => '299'], $this->leaf);
    storefrontProduct(['name' => 'Fancy Kurti'], ['price' => '1499', 'mrp' => '1999'], $this->leaf);

    $this->getJson(frontRoute('storefront.category', [$this->leaf->slug]).'?price=-199&partial=1')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('filter_count', 1)
        ->assertJsonStructure(['results_html', 'facets_html', 'facets_mobile_html', 'sort_html', 'next_page_url', 'url'])
        ->assertJson(fn ($json) => $json->where('results_html', fn (string $html): bool => str_contains($html, 'Cheap Kurti') && ! str_contains($html, 'Fancy Kurti'))->etc());
});

test('search shows matches, is not indexed by robots and offers chips to remove filters', function () {
    storefrontProduct(['name' => 'Printed Cotton Kurti'], [], $this->leaf);
    storefrontProduct(['name' => 'Silk Saree']);

    $this->get(frontRoute('storefront.search').'?q=kurti&cod=1')
        ->assertSuccessful()
        ->assertViewIs('storefront::frontend.search')
        ->assertSee('Results for “kurti”', false)
        ->assertSee('Printed Cotton Kurti')
        ->assertDontSee('Silk Saree')
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('Remove filter Pay on Delivery');
});

test('a search without results suggests a popular spelling and categories', function () {
    storefrontProduct(['name' => 'Printed Cotton Kurti'], [], $this->leaf);
    SearchTermStat::query()->create(['term' => 'kurti', 'searches_7d' => 9, 'searches_30d' => 20, 'avg_results' => 4]);

    $this->get(frontRoute('storefront.search').'?q=kurtee')
        ->assertSuccessful()
        ->assertSee('No results for “kurtee”', false)
        ->assertSee('Did you mean')
        ->assertSee('“kurti”', false)
        ->assertSee('Women');
});

test('the store page lists the seller\'s products and hides other stores', function () {
    [$seller] = sellerWithOwner();
    storefrontProduct(['seller_id' => $seller->id, 'name' => 'Store Kurti']);
    storefrontProduct(['name' => 'Elsewhere Saree']);

    $this->get(frontRoute('storefront.store.show', [$seller->store_slug]))
        ->assertSuccessful()
        ->assertSee($seller->store_name)
        ->assertSee('Store Kurti')
        ->assertSee('1 product')
        ->assertDontSee('Elsewhere Saree');

    [$pending] = sellerWithOwner('pendingVerification');
    $this->get(frontRoute('storefront.store.show', [$pending->store_slug]))->assertNotFound();
});

test('a collection page applies its rules', function () {
    storefrontProduct(['name' => 'Budget Tee'], ['price' => '149', 'mrp' => '299']);
    storefrontProduct(['name' => 'Premium Tee'], ['price' => '899', 'mrp' => '999']);
    $collection = ProductCollection::factory()->create(['name' => 'Under 199', 'rules' => ['max_price' => 199]]);
    $disabled = ProductCollection::factory()->disabled()->create();

    $this->get(frontRoute('storefront.collection', [$collection->slug]))
        ->assertSuccessful()
        ->assertSee('Under 199')
        ->assertSee('Budget Tee')
        ->assertDontSee('Premium Tee');

    $this->get(frontRoute('storefront.collection', [$disabled->slug]))->assertNotFound();
});

test('the CMS page catch-all does not swallow the storefront routes', function () {
    $this->get(frontRoute('storefront.search'))->assertSuccessful()->assertViewIs('storefront::frontend.search');
    $this->getJson(frontRoute('storefront.recently_viewed'))->assertSuccessful()->assertJsonPath('count', 0);
});

test('the products API returns cards, meta and facets', function () {
    storefrontProduct(['name' => 'Printed Cotton Kurti'], ['price' => '349', 'mrp' => '699'], $this->leaf);

    $this->getJson(route('storefront_api.search', ['category' => $this->root->slug, 'sort' => 'price_asc']))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.sort', 'price_asc')
        ->assertJsonPath('data.0.name', 'Printed Cotton Kurti')
        ->assertJsonPath('data.0.price', '349.00')
        ->assertJsonPath('data.0.discount_percent', 50)
        ->assertJsonStructure(['facets' => ['categories', 'attributes', 'brands', 'price', 'rating', 'discount', 'cod']]);

    $this->getJson(route('storefront_api.search', ['category' => 'missing']))->assertNotFound();
});
