<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Support\StorefrontCache;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

test('a page of 40 cards costs the same handful of queries as a page of 2', function () {
    $category = CatalogCategory::factory()->create();
    $brand = CatalogBrand::factory()->verified()->create();

    $count = function (int $products) use ($category, $brand): int {
        DB::table('product_search_index')->delete();
        foreach (range(1, $products) as $index) {
            storefrontProduct(['name' => 'Card '.$index, 'catalog_brand_id' => $brand->id], [], $category);
        }

        app(StorefrontCache::class)->flush('listing');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = app(ProductSearchEngine::class)->run(new ListingQuery(categoryId: $category->id, categoryPath: $category->path, perPage: 40));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($result->cards)->toHaveCount(min(40, $products));

        return $queries;
    };

    $small = $count(2);
    $large = $count(40);

    expect($large)->toBe($small)->toBeLessThanOrEqual(10);
});

test('the category page renders 40 cards without lazy loading', function () {
    $category = CatalogCategory::factory()->create();
    foreach (range(1, 40) as $index) {
        storefrontProduct(['name' => 'Kurti '.$index], [], $category);
    }

    DB::enableQueryLog();
    $this->get(frontRoute('storefront.category', [$category->slug]))->assertSuccessful()->assertSee('Kurti 40');
    $queries = count(DB::getQueryLog());

    // Header, menus, settings and the listing (search, cards, facets) together stay bounded.
    expect($queries)->toBeLessThan(60);
});
