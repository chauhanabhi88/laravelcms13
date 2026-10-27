<?php

use Illuminate\Http\UploadedFile;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Services\ProductModerationService;
use Modules\Product\Support\Import\ImageFetcher;
use Modules\Seller\Models\Seller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/Helpers.php';
require_once __DIR__.'/../../Seller/tests/Helpers.php';

if (! function_exists('jpegBytes')) {
    /**
     * A real JPEG of the given size.
     */
    function jpegBytes(int $width = 600, int $height = 800): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    /**
     * Image URLs resolve every host to a public address (no DNS in tests).
     */
    function useFixedImageResolver(string $address = '93.184.216.34'): void
    {
        app()->instance(ImageFetcher::class, new ImageFetcher(app(ProductImageService::class), fn (string $host): array => [$address]));
    }

    /**
     * A spreadsheet upload built from rows (the first row is the heading row).
     *
     * @param  list<list<string|int|float|null>>  $rows
     */
    function spreadsheetUpload(array $rows, string $format = 'xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'upl').'.'.$format;
        ($format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'catalog.'.$format, null, null, true);
    }

    /**
     * A product created, submitted and approved (live).
     *
     * @param  array<string, mixed>  $catalog  from productCatalog()
     * @param  array<string, mixed>  $overrides
     */
    function liveCatalogProduct(Seller $seller, array $catalog, array $overrides = []): Product
    {
        $product = app(ProductFactoryService::class)->create(productData($seller, $catalog, $overrides));
        app(ProductModerationService::class)->submit($product);

        return app(ProductModerationService::class)->approve($product, productAdmin());
    }
}
