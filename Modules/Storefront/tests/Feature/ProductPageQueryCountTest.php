<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Product\Models\Product;
use Modules\Storefront\Support\StorefrontUrls;
use Modules\Storefront\ViewModels\ProductPageViewModel;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

/**
 * Queries spent by ProductPageViewModel::load() on a warm cache.
 */
function pdpQueryCount(Product $product): int
{
    pdpPage($product);

    $product = $product->fresh();
    $page = app(ProductPageViewModel::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $page->load($product);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('the view model loads a product in at most 12 queries, whatever its size', function () {
    ['product' => $small] = pdpProduct(fn (array $catalog) => [
        productVariant($catalog, 'Q-S-'.uniqid(), 'S', 'Red'),
    ], ['brandId' => CatalogBrand::factory()->create()->id]);

    ['product' => $large] = pdpProduct(fn (array $catalog) => [
        productVariant($catalog, 'Q1-'.uniqid(), 'S', 'Red'),
        productVariant($catalog, 'Q2-'.uniqid(), 'M', 'Red'),
        productVariant($catalog, 'Q3-'.uniqid(), 'L', 'Red'),
        productVariant($catalog, 'Q4-'.uniqid(), 'S', 'Blue'),
        productVariant($catalog, 'Q5-'.uniqid(), 'M', 'Blue'),
        productVariant($catalog, 'Q6-'.uniqid(), 'L', 'Blue'),
    ], ['brandId' => CatalogBrand::factory()->create()->id]);

    $smallQueries = pdpQueryCount($small);
    $largeQueries = pdpQueryCount($large);

    expect($largeQueries)->toBe($smallQueries)->toBeLessThanOrEqual(12);
});

test('the page renders without lazy loading and with bounded queries', function () {
    ['product' => $product] = pdpProduct();

    DB::enableQueryLog();
    $this->get(StorefrontUrls::product($product->slug, $product->id))->assertSuccessful();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Header, menus and settings plus the product page together stay bounded.
    expect($queries)->toBeLessThan(60);
});
