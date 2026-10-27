<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
});

test('catalog routes follow the seller route convention', function () {
    $route = Route::getRoutes()->getByName('seller.product.index');

    expect($route->uri())->toBe('supplier/catalog/products')
        ->and($route->gatherMiddleware())->toContain('auth:seller', 'seller.active', 'seller.can:catalog');
});

test('the product list shows status tabs with counts and filters by tab and search', function () {
    $live = liveCatalogProduct($this->seller, $this->catalog);
    $draft = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, [
        'translations' => ['en' => ['name' => 'Draft Saree']],
        'variants' => [productVariant($this->catalog, 'SAREE-1', 'L', 'Blue')],
    ]));

    $this->get(route('seller.product.index'))
        ->assertSuccessful()
        ->assertSee('Printed Cotton Kurti')
        ->assertSee('Draft Saree')
        ->assertSee(trans('product::seller.products.tabs.live'));

    $this->get(route('seller.product.index', ['tab' => 'draft']))->assertSee('Draft Saree')->assertDontSee('Printed Cotton Kurti');
    $this->get(route('seller.product.index', ['q' => 'SAREE-1']))->assertSee('Draft Saree')->assertDontSee('Printed Cotton Kurti');

    $this->getJson(route('seller.product.filters', ['tab' => 'live']))
        ->assertSuccessful()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Printed Cotton Kurti') && ! str_contains($html, 'Draft Saree'));

    expect($live->id)->not->toBe($draft->id);
});

test('out of stock lists live products without available stock', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'OOS-1', 'S', 'Red', stock: 0)]]);

    $this->get(route('seller.product.index', ['tab' => 'out_of_stock']))->assertSee('Printed Cotton Kurti');
    expect($product->fresh()->total_stock)->toBe(0);
});

test('the seller pauses and resumes a live product', function () {
    $product = liveCatalogProduct($this->seller, $this->catalog);

    $this->post(route('seller.product.pause', [$product->id]))->assertSessionHas('success');
    expect($product->fresh()->status)->toBe(ProductStatus::Paused);

    $this->post(route('seller.product.resume', [$product->id]))->assertSessionHas('success');
    expect($product->fresh()->status)->toBe(ProductStatus::Live);

    $draft = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'D-1', 'L', 'Blue')]]));
    $this->post(route('seller.product.pause', [$draft->id]))->assertSessionHas('error');
});

test('deleting a never-published draft frees its seller SKUs; live products cannot be deleted', function () {
    $draft = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));

    $this->delete(route('seller.product.delete', [$draft->id]))->assertRedirect(route('seller.product.index'));

    expect(Product::query()->withTrashed()->find($draft->id))->toBeNull()
        ->and(ProductVariant::query()->where('seller_sku', 'KRT-S-RED')->exists())->toBeFalse();

    $live = liveCatalogProduct($this->seller, $this->catalog);
    $this->delete(route('seller.product.delete', [$live->id]))->assertSessionHas('error');
    expect($live->fresh())->not->toBeNull();
});

test('a rejected product shows the reason and a fix & resubmit link', function () {
    $product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));
    app(ProductModerationService::class)->submit($product);
    app(ProductModerationService::class)->reject($product, productAdmin(), 'Photos are blurry');

    $this->get(route('seller.product.index', ['tab' => 'rejected']))
        ->assertSee('Photos are blurry')
        ->assertSee(trans('product::seller.products.actions.fix'))
        ->assertSee(route('seller.listing.step', [$product->id, 'review']), false);
});

test('the sidebar shows the catalog section and the dashboard the low-stock card', function () {
    liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'LOW-1', 'S', 'Red', stock: 2)]]);

    $this->get(route('seller.dashboard'))
        ->assertSuccessful()
        ->assertSee(trans('product::seller.menu.catalog'))
        ->assertSee(trans('product::seller.menu.bulk_upload'))
        ->assertSee(trans('product::seller.dashboard.low_stock'))
        ->assertSee('1 SKU')
        ->assertSee(route('seller.inventory.index', ['stock' => 'low']), false);
});
