<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\CatalogUploadRowError;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Notifications\CatalogUploadFinished;
use Modules\Product\Support\Import\CatalogTemplate;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('private');
    Notification::fake();
    useFixedImageResolver();
    Http::fake(['images.example.test/*' => fn () => Http::response(jpegBytes(600, 800), 200, ['Content-Type' => 'image/jpeg'])]);

    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
    $this->template = CatalogTemplate::for($this->catalog['category']);
});

/**
 * One template row from column key => value (everything else blank).
 *
 * @param  array<string, mixed>  $values
 * @return list<mixed>
 */
function templateRow(CatalogTemplate $template, array $values): array
{
    return array_map(fn (array $column) => $values[$column['key']] ?? null, $template->columns());
}

/**
 * @return array<string, mixed>
 */
function kurtiRow(array $catalog, string $group, string $sku, string $size, string $colour, array $overrides = []): array
{
    return $overrides + [
        'group_id' => $group,
        'product_name' => 'Kurti '.$group,
        'description' => 'Cotton kurti',
        'highlights' => 'Cotton|Machine wash',
        'image_url_1' => 'https://images.example.test/'.$group.'.jpg',
        'seller_sku' => $sku,
        'mrp' => 999,
        'price' => 499,
        'stock' => 10,
        'weight_grams' => 300,
        'attr_'.$catalog['size'] => $size,
        'attr_'.$catalog['colour'] => $colour,
        'attr_'.$catalog['fabric'] => 'Cotton',
    ];
}

function uploadCatalog(array $catalog, array $rows, bool $draft = false): CatalogUpload
{
    test()->post(route('seller.upload.store'), [
        'catalog_category_id' => $catalog['category']->id,
        'file' => spreadsheetUpload($rows),
        'save_as_draft' => $draft ? '1' : '0',
    ])->assertRedirect();

    return CatalogUpload::query()->latest('id')->firstOrFail();
}

test('3 groups with 1 invalid: 2 products created, the bad row reported, counts and report right', function () {
    $c = $this->catalog;
    $rows = [
        $this->template->headings(),
        templateRow($this->template, $this->template->exampleRow()),
        templateRow($this->template, kurtiRow($c, 'G1', 'G1-S-RED', 'S', 'Red')),
        templateRow($this->template, kurtiRow($c, 'G1', 'G1-M-RED', 'M', 'Red', ['product_name' => 'Different name'])),
        templateRow($this->template, kurtiRow($c, 'G2', 'G2-L-BLUE', 'L', 'Blue')),
        templateRow($this->template, kurtiRow($c, 'G3', 'G3-XXL', 'XXL', 'Red')),
    ];

    $upload = uploadCatalog($c, $rows);

    expect($upload)
        ->status->toBe(CatalogUpload::STATUS_COMPLETED_WITH_ERRORS)
        ->total_rows->toBe(4)
        ->success_rows->toBe(3)
        ->failed_rows->toBe(1)
        ->error_report_path->not->toBeNull();

    $products = Product::query()->forSeller($this->seller->id)->with(['translations', 'variants', 'images'])->orderBy('id')->get();
    expect($products)->toHaveCount(2)
        ->and($products->pluck('status')->all())->toBe([ProductStatus::PendingQc, ProductStatus::PendingQc])
        ->and($products->first()->name)->toBe('Kurti G1')
        ->and($products->first()->variants->pluck('seller_sku')->all())->toBe(['G1-S-RED', 'G1-M-RED'])
        ->and($products->first()->images)->toHaveCount(1)
        ->and($products->first()->highlights)->toBe(['Cotton', 'Machine wash']);

    $problems = CatalogUploadRowError::query()->where('catalog_upload_id', $upload->id)->get();
    expect($problems->where('level', 'error')->pluck('row_number')->all())->toBe([6])
        ->and($problems->where('level', 'error')->first()->column)->toBe('attr_'.$c['size'])
        ->and($problems->where('level', 'warning')->pluck('row_number')->all())->toBe([4]);

    Storage::disk('private')->assertExists($upload->error_report_path);
    $report = Excel::toArray(new class implements ToArray
    {
        public function array(array $array): void {}
    }, $upload->error_report_path, 'private')[0];
    expect($report)->toHaveCount(2)
        ->and(end($report[0]))->toBe('Errors')
        ->and($report[1][0])->toBe('G3')
        ->and(end($report[1]))->toContain('XXL');

    Http::assertSentCount(2);
    Notification::assertSentTo($this->owner, CatalogUploadFinished::class);

    $this->get(route('seller.upload.show', [$upload->id]))->assertSuccessful()->assertSee('XXL')->assertSee('Size');
    $this->get(route('seller.upload.index'))->assertSuccessful()->assertSee('catalog.xlsx');
});

test('a group with a failing image URL is not saved', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response('nope', 404)]);

    $upload = uploadCatalog($this->catalog, [
        $this->template->headings(),
        templateRow($this->template, kurtiRow($this->catalog, 'G1', 'G1-S-RED', 'S', 'Red')),
    ]);

    expect($upload->status)->toBe(CatalogUpload::STATUS_FAILED)
        ->and(Product::query()->forSeller($this->seller->id)->exists())->toBeFalse()
        ->and(CatalogUploadRowError::query()->where('catalog_upload_id', $upload->id)->value('column'))->toBe('image_url_1');
});

test('re-uploading the same seller SKUs updates the draft in place', function () {
    $rows = fn (string $price) => [
        $this->template->headings(),
        templateRow($this->template, kurtiRow($this->catalog, 'G1', 'G1-S-RED', 'S', 'Red', ['price' => $price])),
    ];

    uploadCatalog($this->catalog, $rows('499'), draft: true);
    $variant = ProductVariant::query()->where('seller_sku', 'G1-S-RED')->sole();

    $second = uploadCatalog($this->catalog, $rows('449'), draft: true);

    $updated = ProductVariant::query()->where('seller_sku', 'G1-S-RED')->sole();
    expect($second->status)->toBe(CatalogUpload::STATUS_COMPLETED)
        ->and(Product::query()->forSeller($this->seller->id)->count())->toBe(1)
        ->and($updated->id)->toBe($variant->id)
        ->and($updated->price->toDecimal())->toBe('449.00');
    expect(Product::query()->forSeller($this->seller->id)->sole()->status)->toBe(ProductStatus::Draft);
});

test('re-uploading a live product keeps it live and parks content changes for QC', function () {
    $live = liveCatalogProduct($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'LIVE-S-RED', 'S', 'Red')]]);

    $upload = uploadCatalog($this->catalog, [
        $this->template->headings(),
        templateRow($this->template, kurtiRow($this->catalog, 'G1', 'LIVE-S-RED', 'S', 'Red', ['product_name' => 'Brand new name', 'price' => 459])),
    ]);

    $live = $live->fresh(['translations', 'variants']);
    expect($upload->status)->toBe(CatalogUpload::STATUS_COMPLETED)
        ->and($live->status)->toBe(ProductStatus::Live)
        ->and($live->name)->toBe('Printed Cotton Kurti')
        ->and($live->pending_changes['translations']['en']['name'])->toBe('Brand new name')
        ->and($live->variants->first()->price->toDecimal())->toBe('459.00');
});

test('a file without the required columns fails as a whole', function () {
    $upload = uploadCatalog($this->catalog, [['Group ID', 'Product name'], ['G1', 'Kurti']]);

    expect($upload->status)->toBe(CatalogUpload::STATUS_FAILED)
        ->and(CatalogUploadRowError::query()->where('catalog_upload_id', $upload->id)->value('message'))->toContain('Seller SKU');
});

test('CSV uploads are read too', function () {
    $this->post(route('seller.upload.store'), [
        'catalog_category_id' => $this->catalog['category']->id,
        'file' => spreadsheetUpload([
            $this->template->headings(),
            templateRow($this->template, kurtiRow($this->catalog, 'G1', 'CSV-S-RED', 'S', 'Red')),
        ], 'csv'),
    ])->assertRedirect();

    expect(CatalogUpload::query()->latest('id')->first()->status)->toBe(CatalogUpload::STATUS_COMPLETED)
        ->and(ProductVariant::query()->where('seller_sku', 'CSV-S-RED')->exists())->toBeTrue();
});

test('the upload form validates the category and the file', function () {
    $this->post(route('seller.upload.store'), ['catalog_category_id' => $this->catalog['root']->id, 'file' => spreadsheetUpload([['a']])])
        ->assertSessionHasErrors('catalog_category_id');
    $this->post(route('seller.upload.store'), ['catalog_category_id' => $this->catalog['category']->id])
        ->assertSessionHasErrors('file');

    expect(CatalogUpload::query()->count())->toBe(0);
});
