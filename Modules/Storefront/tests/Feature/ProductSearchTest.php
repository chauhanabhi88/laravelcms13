<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Services\Search\ProductSearch;
use Modules\Storefront\Services\Search\TextQuery;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    $this->search = app(ProductSearchEngine::class);
});

function searchIds(ListingQuery $query): array
{
    return app(ProductSearchEngine::class)->run($query)->productIds;
}

test('a text search matches product names and ignores the rest', function () {
    $kurti = storefrontProduct(['name' => 'Printed Cotton Kurti']);
    storefrontProduct(['name' => 'Silk Saree']);

    $result = $this->search->run(new ListingQuery(q: 'cotton kurti'));

    expect($result->productIds)->toBe([$kurti->id])
        ->and($result->total)->toBe(1)
        ->and($result->cards[0]['name'])->toBe('Printed Cotton Kurti');
});

test('full-text strategies run before the LIKE fallback', function () {
    storefrontProduct(['name' => 'Printed Cotton Kurti']);
    DB::enableQueryLog();

    $result = $this->search->run(new ListingQuery(q: 'kurti'));

    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    expect($sql)->toContain('IN NATURAL LANGUAGE MODE', 'IN BOOLEAN MODE')
        // InnoDB full-text does not see the uncommitted test rows, so the name LIKE answered.
        ->and($result->textMode)->toBe(ProductSearch::MODE_LIKE)
        ->and($result->total)->toBe(1);
});

test('user input cannot inject boolean operators', function () {
    expect(TextQuery::booleanPrefix('+kurti -"red" (cotton)*'))->toBe('+kurti* +red* +cotton*')
        ->and(TextQuery::tokens('ab'))->toBe(['ab'])
        ->and(TextQuery::indexableTokens('ab'))->toBe([]);
});

test('a category listing includes the descendants', function () {
    $root = CatalogCategory::factory()->create(['name' => 'Women']);
    $leaf = CatalogCategory::factory()->child($root)->create(['name' => 'Kurtis']);
    $other = CatalogCategory::factory()->create(['name' => 'Men']);

    $inLeaf = storefrontProduct([], [], $leaf);
    storefrontProduct([], [], $other);

    expect(searchIds(new ListingQuery(categoryId: $root->id, categoryPath: $root->fresh()->path)))->toBe([$inLeaf->id]);
});

test('attribute filters are OR within an attribute and AND across attributes', function () {
    [$seller] = sellerWithOwner();
    $catalog = productCatalog();
    $redSmall = liveCatalogProduct($seller, $catalog, ['variants' => [productVariant($catalog, 'A-S-R', 'S', 'Red')]]);
    $blueLarge = liveCatalogProduct($seller, $catalog, ['variants' => [productVariant($catalog, 'B-L-B', 'L', 'Blue')]]);
    $redLarge = liveCatalogProduct($seller, $catalog, ['variants' => [productVariant($catalog, 'C-L-R', 'L', 'Red')]]);
    $o = $catalog['options'];

    $either = searchIds(new ListingQuery(attributes: [$catalog['size'] => [$o['S'], $o['L']]]));
    $both = searchIds(new ListingQuery(attributes: [$catalog['size'] => [$o['L']], $catalog['colour'] => [$o['Red']]]));

    expect($either)->toEqualCanonicalizing([$redSmall->id, $blueLarge->id, $redLarge->id])
        ->and($both)->toBe([$redLarge->id]);
});

test('price, rating, discount, COD and brand filters narrow the results', function () {
    $brand = CatalogBrand::factory()->create();
    $cheap = storefrontProduct([], ['price' => '149', 'mrp' => '299']);
    $mid = storefrontProduct(['catalog_brand_id' => $brand->id], ['price' => '399', 'mrp' => '999']);
    $dear = storefrontProduct(['is_cod_allowed' => 2], ['price' => '1499', 'mrp' => '1599']);
    storefrontUpdate($mid, ['rating_avg' => '4.40', 'rating_count' => 12]);

    expect(searchIds(new ListingQuery(priceFrom: 199, priceTo: 999)))->toBe([$mid->id])
        ->and(searchIds(new ListingQuery(priceTo: 199)))->toBe([$cheap->id])
        ->and(searchIds(new ListingQuery(rating: 4)))->toBe([$mid->id])
        ->and(searchIds(new ListingQuery(discount: 50)))->toEqualCanonicalizing([$cheap->id, $mid->id])
        ->and(searchIds(new ListingQuery(codOnly: true)))->not->toContain($dear->id)
        ->and(searchIds(new ListingQuery(brandIds: [$brand->id])))->toBe([$mid->id]);
});

test('each sort order', function (string $sort, array $expected) {
    $a = storefrontProduct(['name' => 'Alpha'], ['price' => '300', 'mrp' => '400']);
    $b = storefrontProduct(['name' => 'Bravo'], ['price' => '100', 'mrp' => '1000']);
    $c = storefrontProduct(['name' => 'Charlie'], ['price' => '200', 'mrp' => '200']);
    storefrontUpdate($a, ['published_at' => now()->subDays(3), 'rating_avg' => '4.80', 'rating_count' => 3, 'sold_count' => 50]);
    storefrontUpdate($b, ['published_at' => now()->subDay(), 'rating_avg' => '3.90', 'rating_count' => 40, 'sold_count' => 5]);
    storefrontUpdate($c, ['published_at' => now()->subDays(2), 'rating_avg' => '4.20', 'rating_count' => 10, 'sold_count' => 20]);

    $names = ['a' => $a->id, 'b' => $b->id, 'c' => $c->id];

    expect(searchIds(new ListingQuery(sort: $sort)))->toBe(array_map(fn (string $key): int => $names[$key], $expected));
})->with([
    'popularity' => ['relevance', ['a', 'c', 'b']],
    'new arrivals' => ['new', ['b', 'c', 'a']],
    'price low to high' => ['price_asc', ['b', 'c', 'a']],
    'price high to low' => ['price_desc', ['a', 'c', 'b']],
    // Products with at least 5 ratings rank first.
    'rating' => ['rating', ['c', 'b', 'a']],
    'discount' => ['discount', ['b', 'a', 'c']],
]);

test('out-of-stock products are listed after the available ones', function () {
    $soldOut = storefrontProduct(['name' => 'Sold Out'], ['price' => '100', 'mrp' => '200']);
    $available = storefrontProduct(['name' => 'Available'], ['price' => '900', 'mrp' => '1000']);
    storefrontUpdate($soldOut, [], ['stock' => 0]);

    expect(searchIds(new ListingQuery(sort: 'price_asc')))->toBe([$available->id, $soldOut->id]);
});

test('pages hold per_page cards and report whether more follow', function () {
    foreach (range(1, 5) as $index) {
        storefrontProduct(['name' => 'Tee '.$index]);
    }

    $first = $this->search->run(new ListingQuery(perPage: 2));
    $last = $this->search->run(new ListingQuery(perPage: 2, page: 3), false);

    expect($first->productIds)->toHaveCount(2)
        ->and($first->hasMore)->toBeTrue()
        ->and($first->total)->toBe(5)
        ->and($last->productIds)->toHaveCount(1)
        ->and($last->hasMore)->toBeFalse()
        ->and($last->total)->toBeNull();
});

test('a manual collection keeps its product order and an empty one matches nothing', function () {
    $first = storefrontProduct();
    $second = storefrontProduct();

    expect(searchIds(new ListingQuery(constraints: ['product_ids' => [$second->id, $first->id]])))->toBe([$second->id, $first->id])
        ->and(searchIds(new ListingQuery(constraints: ['product_ids' => []])))->toBe([]);
});

test('the query is parsed from whitelisted request parameters', function () {
    [, $sizes] = productAttribute('size', ['S', 'M']);
    $attributeId = (int) DB::table('attribute')->where('id', DB::table('attribute_option')->where('id', $sizes['S'])->value('attribute_id'))->value('id');
    $code = (string) DB::table('attribute')->where('id', $attributeId)->value('code');

    $request = Request::create('/search', 'GET', [
        'q' => '  red   kurti ', 'price' => '499-199', 'rating' => '4', 'discount' => '33', 'cod' => '1',
        'brand' => '3,x,3', 'sort' => 'price_desc', 'page' => '2', $code => $sizes['M'].','.$sizes['S'], 'unknown' => '5',
    ]);

    $query = ListingQuery::fromRequest($request);

    expect($query->q)->toBe('red kurti')
        ->and([$query->priceFrom, $query->priceTo])->toBe([199, 499])
        ->and($query->rating)->toBe(4)
        ->and($query->discount)->toBeNull()
        ->and($query->codOnly)->toBeTrue()
        ->and($query->brandIds)->toBe([3])
        ->and($query->sort)->toBe('price_desc')
        ->and($query->page)->toBe(2)
        ->and($query->attributes)->toBe([$attributeId => [$sizes['S'], $sizes['M']]])
        ->and($query->urlParams())->toMatchArray(['q' => 'red kurti', 'price' => '199-499', $code => $sizes['S'].','.$sizes['M'], 'sort' => 'price_desc']);
});
