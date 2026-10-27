<?php

use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Storefront\Services\Search\FacetService;
use Modules\Storefront\Services\Search\ListingQuery;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    [$this->seller] = sellerWithOwner();
    $this->catalog = productCatalog();
    $o = $this->catalog['options'];
    $this->o = $o;

    $this->redSmall = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'A-S-R', 'S', 'Red', '299', '599')]]);
    $this->blueLarge = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'B-L-B', 'L', 'Blue', '799', '999')]]);
    $this->redLarge = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'C-L-R', 'L', 'Red', '1299', '1999')]]);

    $leaf = $this->catalog['category'];
    $this->categoryQuery = fn (array $values = []) => new ListingQuery(...$values + ['categoryId' => $leaf->id, 'categoryPath' => $leaf->path]);
});

function facetOptions(array $facets, int $attributeId): array
{
    $attribute = collect($facets['attributes'])->firstWhere('id', $attributeId);

    return collect($attribute['options'] ?? [])->mapWithKeys(fn (array $option): array => [$option['name'] => $option['count']])->all();
}

test('attribute counts leave out the attribute\'s own filter but apply the others', function () {
    $facets = app(FacetService::class)->facets(($this->categoryQuery)([
        'attributes' => [$this->catalog['size'] => [$this->o['L']]],
    ]));

    // Size still offers S (counted without the size filter)…
    expect(facetOptions($facets, $this->catalog['size']))->toBe(['S' => 1, 'L' => 2])
        // …while colour counts are within size L.
        ->and(facetOptions($facets, $this->catalog['colour']))->toBe(['Red' => 1, 'Blue' => 1])
        ->and(facetOptions($facets, $this->catalog['fabric']))->toBe(['Cotton' => 2]);
});

test('price range, presets, rating, discount and COD counts', function () {
    $facets = app(FacetService::class)->facets(($this->categoryQuery)(['priceTo' => 499]));

    expect($facets['price']['min'])->toBe(299)
        ->and($facets['price']['max'])->toBe(1299)
        ->and(collect($facets['price']['presets'])->pluck('count')->all())->toBe([0, 1, 1, 1])
        // Discount buckets are counted with the price filter applied (only the ₹299 product, 50% off).
        ->and(collect($facets['discount'])->pluck('count', 'value')->all())->toBe([10 => 1, 30 => 1, 50 => 1, 70 => 0])
        ->and($facets['cod'])->toBe(1);
});

test('a search without a category offers the common filterable attributes', function () {
    $facets = app(FacetService::class)->facets(new ListingQuery(q: 'kurti'));

    expect(collect($facets['attributes'])->pluck('id')->all())->toContain($this->catalog['size'], $this->catalog['colour'], $this->catalog['fabric']);
});

test('category facet counts roll up to the direct children', function () {
    $root = $this->catalog['root'];
    $sibling = CatalogCategory::factory()->child($root)->create(['name' => 'Sarees']);
    storefrontProduct([], [], $sibling);

    $facets = app(FacetService::class)->facets(new ListingQuery(categoryId: $root->id, categoryPath: $root->fresh()->path));
    $children = collect($facets['categories']['children'])->pluck('count', 'name')->all();

    expect($children)->toBe(['Kurtis' => 3, 'Sarees' => 1]);

    $top = app(FacetService::class)->facets(new ListingQuery);
    expect(collect($top['categories']['children'])->firstWhere('id', $root->id)['count'])->toBe(4);
});

test('brand counts include the selected brands', function () {
    $brand = CatalogBrand::factory()->create(['name' => 'Loomcraft']);
    storefrontProduct(['catalog_brand_id' => $brand->id]);

    $facets = app(FacetService::class)->facets(new ListingQuery(brandIds: [$brand->id]));

    expect($facets['brands'])->toBe([['id' => $brand->id, 'name' => 'Loomcraft', 'count' => 1]]);
});
