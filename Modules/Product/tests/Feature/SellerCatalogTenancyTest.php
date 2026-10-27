<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Services\ProductFactoryService;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('private');
    $this->catalog = productCatalog();
    [$this->sellerA, $this->ownerA] = sellerWithOwner();
    [$this->sellerB, $this->ownerB] = sellerWithOwner();
    $this->productA = liveCatalogProduct($this->sellerA, $this->catalog);
    $this->draftA = app(ProductFactoryService::class)->create(productData($this->sellerA, $this->catalog, [
        'variants' => [productVariant($this->catalog, 'DRAFT-1', 'L', 'Blue')],
    ]));
});

test('seller B cannot see, edit, pause, delete or add images to seller A\'s products', function () {
    $this->actingAs($this->ownerB, 'seller');
    $variant = $this->productA->variants()->first();

    $this->get(route('seller.listing.step', [$this->productA->id, 'details']))->assertNotFound();
    $this->get(route('seller.product.edit', [$this->productA->id]))->assertNotFound();
    $this->post(route('seller.listing.save', [$this->draftA->id, 'details']), ['translations' => ['en' => ['name' => '']]])->assertNotFound();
    $this->postJson(route('seller.listing.variants.save', [$this->draftA->id]), ['variants' => []])->assertNotFound();
    $this->post(route('seller.listing.submit', [$this->draftA->id]))->assertNotFound();
    $this->post(route('seller.product.pause', [$this->productA->id]))->assertNotFound();
    $this->delete(route('seller.product.delete', [$this->draftA->id]))->assertNotFound();
    $this->post(route('seller.image.upload', [$this->draftA->id]), ['image' => UploadedFile::fake()->image('x.jpg', 600, 600)], ['Accept' => 'application/json'])->assertNotFound();
    $this->deleteJson(route('seller.image.delete', [$this->productA->id]), ['path' => 'x.jpg'])->assertNotFound();
    $this->patchJson(route('seller.inventory.update', [$variant->id]), ['stock' => 0])->assertNotFound();

    $this->get(route('seller.product.index'))->assertSuccessful()->assertDontSee('Printed Cotton Kurti');
    $this->get(route('seller.inventory.index'))->assertSuccessful()->assertDontSee($variant->seller_sku);

    expect($this->productA->fresh()->status)->toBe(ProductStatus::Live)
        ->and($variant->fresh()->stock)->toBe(10)
        ->and($this->draftA->fresh())->not->toBeNull();
});

test('seller B cannot open seller A\'s uploads', function () {
    $upload = CatalogUpload::factory()->for($this->sellerA)->completed(3, 1)->create(['error_report_path' => 'catalog_uploads/1/r.xlsx']);
    Storage::disk('private')->put($upload->file_path, 'data');
    Storage::disk('private')->put($upload->error_report_path, 'data');

    $this->actingAs($this->ownerB, 'seller');
    $this->get(route('seller.upload.show', [$upload->id]))->assertNotFound();
    $this->get(route('seller.upload.status', [$upload->id]))->assertNotFound();
    $this->get(route('seller.upload.file', [$upload->id]))->assertNotFound();
    $this->get(route('seller.upload.error_report', [$upload->id]))->assertNotFound();

    $this->actingAs($this->ownerA, 'seller');
    $this->get(route('seller.upload.show', [$upload->id]))->assertSuccessful();
    $this->get(route('seller.upload.error_report', [$upload->id]))->assertSuccessful();
});

test('only owners and catalog staff open the catalog tools', function () {
    $orders = SellerUser::factory()->for($this->sellerA)->role('orders')->create();
    $catalogStaff = SellerUser::factory()->for($this->sellerA)->role('catalog')->create();

    $this->actingAs($orders, 'seller');
    $this->get(route('seller.product.index'))->assertForbidden();
    $this->get(route('seller.inventory.index'))->assertForbidden();
    $this->get(route('seller.upload.create'))->assertForbidden();

    $this->actingAs($catalogStaff, 'seller');
    $this->get(route('seller.product.index'))->assertSuccessful();
    $this->get(route('seller.inventory.index'))->assertSuccessful();
});

test('a suspended seller can look at the catalog but not change it', function () {
    $this->sellerA->forceFill(['status' => SellerStatus::Suspended, 'suspended_reason' => 'Review'])->save();
    $this->actingAs($this->ownerA, 'seller');

    $this->get(route('seller.product.index'))->assertSuccessful();
    $this->post(route('seller.product.pause', [$this->productA->id]))->assertForbidden();
});
