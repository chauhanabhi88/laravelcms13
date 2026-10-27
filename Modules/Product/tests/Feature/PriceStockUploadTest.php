<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\CatalogUploadRowError;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\ProductVariant;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();
    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
    $this->product = liveCatalogProduct($this->seller, $this->catalog);
});

function uploadPriceStock(array $rows): CatalogUpload
{
    test()->post(route('seller.price_stock.store'), ['file' => spreadsheetUpload([['Seller SKU*', 'MRP', 'Selling price', 'Stock'], ...$rows], 'csv')])->assertRedirect();

    return CatalogUpload::query()->latest('id')->firstOrFail();
}

test('prices and stock update at once, blanks keep their value, and stock changes are ledgered', function () {
    $upload = uploadPriceStock([
        ['KRT-S-RED', '899', '449', '25'],
        ['KRT-M-RED', '', '', '3'],
    ]);

    expect($upload->status)->toBe(CatalogUpload::STATUS_COMPLETED)
        ->and($upload->success_rows)->toBe(2)
        ->and($upload->type)->toBe(CatalogUpload::TYPE_PRICE_STOCK);

    $small = ProductVariant::query()->where('seller_sku', 'KRT-S-RED')->sole();
    $medium = ProductVariant::query()->where('seller_sku', 'KRT-M-RED')->sole();

    expect($small->mrp->toDecimal())->toBe('899.00')
        ->and($small->price->toDecimal())->toBe('449.00')
        ->and($small->stock)->toBe(25)
        ->and($medium->price->toDecimal())->toBe('549.00')
        ->and($medium->mrp->toDecimal())->toBe('1099.00')
        ->and($medium->stock)->toBe(3)
        ->and($this->product->fresh()->min_price->toDecimal())->toBe('449.00');

    $movement = InventoryMovement::query()->where('variant_id', $small->id)->latest('id')->first();
    expect($movement->type)->toBe(InventoryMovement::TYPE_ADJUST)
        ->and($movement->quantity)->toBe(15)
        ->and($movement->note)->toBe('Bulk upload #'.$upload->id)
        ->and($movement->actor_id)->toBe($this->owner->id);
});

test('invalid rows are reported and skipped while valid rows apply', function () {
    $upload = uploadPriceStock([
        ['KRT-S-RED', '999', '1200', ''],
        ['KRT-M-RED', '', '', 'many'],
        ['UNKNOWN-SKU', '', '', '5'],
        ['KRT-M-RED', '', '', '4'],
    ]);

    expect($upload->status)->toBe(CatalogUpload::STATUS_FAILED)
        ->and($upload->success_rows)->toBe(0)
        ->and($upload->failed_rows)->toBe(4)
        ->and($upload->error_report_path)->not->toBeNull();

    $errors = CatalogUploadRowError::query()->where('catalog_upload_id', $upload->id)->get()->keyBy('row_number');
    expect($errors[2]->column)->toBe('price')
        ->and($errors[3]->column)->toBe('stock')
        ->and($errors[4]->message)->toContain('UNKNOWN-SKU')
        ->and($errors[5]->message)->toContain('more than once');

    expect(ProductVariant::query()->where('seller_sku', 'KRT-S-RED')->sole()->price->toDecimal())->toBe('499.00');
});

test('another seller\'s SKUs are unknown here', function () {
    [$other] = sellerWithOwner();
    $otherProduct = liveCatalogProduct($other, $this->catalog, ['variants' => [productVariant($this->catalog, 'THEIRS-1', 'S', 'Red')]]);

    $upload = uploadPriceStock([['THEIRS-1', '', '1', '0']]);

    expect($upload->failed_rows)->toBe(1)
        ->and($otherProduct->variants()->first()->stock)->toBe(10);
});

test('the price & stock template lists the seller\'s variants', function () {
    $this->get(route('seller.price_stock.create'))->assertSuccessful();

    $response = $this->get(route('seller.price_stock.template'))->assertSuccessful()->assertDownload('price-stock.csv');
    $csv = $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($csv)->toContain('KRT-S-RED')->toContain('Seller SKU');
});
