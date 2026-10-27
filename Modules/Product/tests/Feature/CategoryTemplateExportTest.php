<?php

use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Product\Exports\CategoryTemplateExport;
use Modules\Product\Support\Import\CatalogTemplate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
});

function loadTemplate(array $catalog): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, Excel::raw(new CategoryTemplateExport(CatalogTemplate::for($catalog['category'])), ExcelFormat::XLSX));

    return IOFactory::load($path);
}

test('headings include the fixed columns and the mapped attributes, axes first and required ones starred', function () {
    $headings = CatalogTemplate::for($this->catalog['category'])->headings();

    expect($headings)->toContain('Group ID*', 'Product name*', 'Seller SKU*', 'MRP*', 'Selling price*', 'Image URL 1*', 'Image URL 2', 'Size*', 'Colour*', 'Fabric*')
        ->and(array_search('Size*', $headings))->toBeLessThan(array_search('Fabric*', $headings));
});

test('the workbook has catalog, instructions and options sheets with dropdowns on the options', function () {
    CatalogBrand::factory()->create(['name' => 'Shree Looms']);
    $spreadsheet = loadTemplate($this->catalog);

    expect($spreadsheet->getSheetNames())->toBe(['Catalog', 'Instructions', 'Options']);

    $catalogSheet = $spreadsheet->getSheetByName('Catalog');
    $headings = $catalogSheet->rangeToArray('A1:'.$catalogSheet->getHighestColumn().'1')[0];
    expect($headings)->toBe(CatalogTemplate::for($this->catalog['category'])->headings())
        ->and($catalogSheet->getCell('A2')->getValue())->toBe(CatalogTemplate::EXAMPLE_GROUP);

    $options = $spreadsheet->getSheetByName('Options')->toArray();
    $columns = array_flip($options[0]);
    $values = fn (string $heading): array => array_values(array_filter(array_column($options, $columns[$heading]), fn ($value) => $value !== null && $value !== '' && $value !== $heading));

    expect($values('Size'))->toBe(['S', 'M', 'L'])
        ->and($values('Colour'))->toBe(['Red', 'Blue'])
        ->and($values('Fabric'))->toBe(['Cotton', 'Rayon'])
        ->and($values('Brand'))->toContain('Shree Looms');

    $sizeColumn = Coordinate::stringFromColumnIndex(array_search('Size*', $headings) + 1);
    $validation = collect($catalogSheet->getDataValidationCollection())->first(fn ($rule, string $range) => $range === $sizeColumn.'2' || str_starts_with($range, $sizeColumn.'2:'));
    expect($validation)->not->toBeNull()
        ->and($validation->getType())->toBe(DataValidation::TYPE_LIST)
        ->and($validation->getFormula1())->toStartWith("'Options'!");
});

test('the template downloads for a listable category only', function () {
    [, $owner] = sellerWithOwner();
    $this->actingAs($owner, 'seller');

    $this->get(route('seller.upload.template', ['category_id' => $this->catalog['category']->id]))
        ->assertSuccessful()
        ->assertDownload();

    $this->get(route('seller.upload.template', ['category_id' => $this->catalog['root']->id]))
        ->assertRedirect(route('seller.upload.create'));
});
