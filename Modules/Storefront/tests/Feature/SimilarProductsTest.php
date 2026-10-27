<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Models\Product;
use Modules\Storefront\Services\Search\SearchIndexer;
use Modules\Storefront\Services\SimilarProductsService;
use Modules\Storefront\Support\StorefrontCache;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    $this->category = CatalogCategory::factory()->create();
    $this->product = storefrontProduct(['name' => 'Base Kurti'], ['price' => '500.00', 'mrp' => '999.00'], $this->category);
});

test('similar products share the leaf category and the price band, in stock, never the product itself', function () {
    $inBand = storefrontProduct(['name' => 'Cheaper Kurti'], ['price' => '360.00'], $this->category);
    $atEdge = storefrontProduct(['name' => 'Pricier Kurti'], ['price' => '650.00'], $this->category);
    storefrontProduct(['name' => 'Too Cheap'], ['price' => '340.00'], $this->category);
    storefrontProduct(['name' => 'Too Dear'], ['price' => '700.00'], $this->category);
    storefrontProduct(['name' => 'Elsewhere'], ['price' => '500.00']);
    Product::factory()->live()->withVariants(1, ['price' => '500.00'])->outOfStock()->inCategory($this->category)->create(['name' => 'Sold Out']);

    $ids = app(SimilarProductsService::class)->similar($this->product);

    expect($ids)->toEqualCanonicalizing([$inBand->id, $atEdge->id]);
});

test('similar products are ordered by popularity and limited', function () {
    $popular = storefrontProduct(['name' => 'Popular'], ['price' => '480.00'], $this->category);
    $quiet = storefrontProduct(['name' => 'Quiet'], ['price' => '520.00'], $this->category);
    storefrontUpdate($popular, ['sold_count' => 50]);

    expect(app(SimilarProductsService::class)->similar($this->product, 1))->toBe([$popular->id])
        ->and(app(SimilarProductsService::class)->similar($this->product, 5))->toBe([$popular->id, $quiet->id]);
});

test('more from this seller lists the seller\'s other visible products', function () {
    $sameSeller = Product::factory()->live()->withVariants(1, ['price' => '2000.00'])->forSeller($this->product->seller)->create(['name' => 'Seller Saree']);
    storefrontProduct(['name' => 'Other Seller'], ['price' => '500.00'], $this->category);
    app(SearchIndexer::class)->index($sameSeller->id);

    expect(app(SimilarProductsService::class)->moreFromSeller($this->product))->toBe([$sameSeller->id]);
});

test('the lazy row endpoint renders cards and drops products hidden after caching', function () {
    $neighbour = storefrontProduct(['name' => 'Neighbour Kurti'], ['price' => '510.00'], $this->category);
    $url = frontRoute('storefront.product.similar', [$this->product->id, ['type' => 'similar']]);

    $this->getJson($url)
        ->assertSuccessful()
        ->assertJsonPath('count', 1)
        ->assertJson(fn ($json) => $json->where('html', fn (string $html) => str_contains($html, 'Neighbour Kurti'))->etc());

    DB::table('product_search_index')->where('product_id', $neighbour->id)->update(['is_visible' => 2]);

    $this->getJson($url)->assertSuccessful()->assertJsonPath('count', 0)->assertJsonPath('html', '');

    $this->getJson(frontRoute('storefront.product.similar', [$this->product->id, ['type' => 'seller']]))
        ->assertSuccessful()
        ->assertJsonPath('count', 0);
});

test('ids are cached per product until the pdp bucket is flushed', function () {
    $service = app(SimilarProductsService::class);
    expect($service->similar($this->product))->toBe([]);

    $late = storefrontProduct(['name' => 'Late Kurti'], ['price' => '500.00'], $this->category);
    expect($service->similar($this->product))->toBe([]);

    app(StorefrontCache::class)->flush('pdp');
    expect($service->similar($this->product))->toBe([$late->id]);
});

test('the endpoint is a 404 for hidden products', function () {
    DB::table('product')->where('id', $this->product->id)->update(['status' => 'paused']);

    $this->getJson(frontRoute('storefront.product.similar', [$this->product->id]))->assertNotFound();
});
