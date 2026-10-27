<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\InventoryService;
use Modules\Product\Services\ProductModerationService;
use Modules\Seller\Services\SellerVerificationService;
use Modules\Storefront\Services\Search\SearchIndexer;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    [$this->seller] = sellerWithOwner();
    $this->catalog = productCatalog();
});

test('an approved product is indexed with its category path, searchable text and facets', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);

    $row = DB::table('product_search_index')->where('product_id', $product->id)->where('locale', 'en')->first();

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Printed Cotton Kurti')
        ->and($row->category_path)->toBe($this->catalog['category']->path)
        ->and($row->seller_id)->toBe($this->seller->id)
        ->and((string) $row->min_price)->toBe('499.00')
        ->and($row->discount)->toBe(50)
        ->and($row->in_stock)->toBe(1)
        ->and($row->is_visible)->toBe(1)
        ->and($row->document)->toContain('Printed Cotton Kurti', 'Women', 'Kurtis', 'Cotton', 'Red');

    $facets = DB::table('product_facet')->where('product_id', $product->id)->get()
        ->map(fn ($facet) => $facet->attribute_id.':'.$facet->attribute_option_id)->all();

    expect($facets)->toEqualCanonicalizing([
        $this->catalog['fabric'].':'.$this->catalog['options']['Cotton'],
        $this->catalog['size'].':'.$this->catalog['options']['S'],
        $this->catalog['size'].':'.$this->catalog['options']['M'],
        $this->catalog['colour'].':'.$this->catalog['options']['Red'],
    ]);
});

test('a sold-out variant no longer offers its size as a facet', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);
    $small = ProductVariant::query()->where('product_id', $product->id)->where('seller_sku', 'KRT-S-RED')->sole();

    app(InventoryService::class)->adjust($small, 0, null, 'Sold elsewhere');

    $sizes = DB::table('product_facet')->where('product_id', $product->id)->where('attribute_id', $this->catalog['size'])->pluck('attribute_option_id')->all();

    expect($sizes)->toBe([$this->catalog['options']['M']]);
});

test('blocked and paused products leave the index', function () {
    $blocked = liveCatalogProduct($this->seller, $this->catalog);
    $paused = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'KRT-L-BLUE', 'L', 'Blue')]]);
    $moderation = app(ProductModerationService::class);

    expect(indexedIds())->toEqual(collect([$blocked->id, $paused->id])->sort()->values()->all());

    $moderation->block($blocked, productAdmin(), 'Policy review');
    $moderation->pause($paused->fresh());

    expect(indexedIds())->toBe([])
        ->and(DB::table('product_facet')->whereIn('product_id', [$blocked->id, $paused->id])->count())->toBe(0);

    $moderation->resume($paused->fresh());

    expect(indexedIds())->toBe([$paused->id]);
});

test('a seller suspension hides their products and reactivation shows them again', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);
    $admin = sellerAdmin();

    app(SellerVerificationService::class)->suspend($this->seller, $admin, 'Quality review');
    expect(DB::table('product_search_index')->where('product_id', $product->id)->value('is_visible'))->toBe(2);

    app(SellerVerificationService::class)->reactivate($this->seller->fresh(), $admin);
    expect(DB::table('product_search_index')->where('product_id', $product->id)->value('is_visible'))->toBe(1);
});

test('moving a category updates the indexed paths', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);
    $newRoot = CatalogCategory::factory()->create(['name' => 'Ethnic Wear']);

    app(CategoryTreeService::class)->move($this->catalog['category'], $newRoot->id);

    expect(DB::table('product_search_index')->where('product_id', $product->id)->value('category_path'))
        ->toBe('/'.$newRoot->id.'/'.$this->catalog['category']->id.'/');
});

test('the reindex command restores missing rows and drops stale ones', function () {
    $live = storefrontProduct(['name' => 'Linen Shirt']);
    $draft = Product::factory()->withVariants()->create();

    DB::table('product_search_index')->delete();
    DB::table('product_search_index')->insert([
        'product_id' => $draft->id, 'locale' => 'en', 'document' => 'stale', 'name' => 'stale', 'catalog_category_id' => $draft->catalog_category_id,
        'category_path' => '/x/', 'seller_id' => $draft->seller_id, 'is_visible' => 1,
    ]);

    $this->artisan('storefront:reindex')->assertSuccessful();

    expect(indexedIds())->toBe([$live->id]);
});

test('the reindex command rejects non-numeric options', function () {
    $this->artisan('storefront:reindex', ['--seller' => 'abc'])->assertFailed();
});

test('the index is written in every content locale', function () {
    expect(SearchIndexer::locales())->toContain('en');

    $product = storefrontProduct(['name' => 'Silk Saree']);

    expect(DB::table('product_search_index')->where('product_id', $product->id)->pluck('locale')->all())
        ->toEqualCanonicalizing(SearchIndexer::locales());
});
