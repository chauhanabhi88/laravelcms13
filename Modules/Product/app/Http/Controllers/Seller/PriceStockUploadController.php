<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Product\Exports\PriceStockTemplateExport;
use Modules\Product\Http\Requests\Seller\PriceStockUploadRequest;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Product\Services\CatalogUploadService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk price & stock update (FR-SCT-005): a CSV / xlsx of Seller SKU, MRP, Price, Stock.
 * Changes apply at once, without QC.
 */
class PriceStockUploadController extends SellerPanelController
{
    public function __construct(
        private readonly SellerCatalogRepository $catalog,
        private readonly CatalogUploadService $uploads,
    ) {}

    public function create(): View
    {
        return view('product::seller.upload.price_stock', [
            'sellerStoreName' => $this->seller()->displayName(),
            'maxRows' => (int) config('product.bulk_max_rows', 5000),
            'maxMb' => (int) config('product.bulk_max_mb', 10),
        ]);
    }

    /**
     * The template, pre-filled with the seller's variants.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new PriceStockTemplateExport($this->catalog->priceStockRows($this->seller()->id)), 'price-stock.csv', ExcelFormat::CSV);
    }

    public function store(PriceStockUploadRequest $request): RedirectResponse
    {
        $upload = $this->uploads->createPriceStock($this->seller(), $this->sellerUser(), $request->file('file'));

        return redirect()->to(frontRoute('seller.upload.show', [$upload->id]))->with('success', trans('product::seller.uploads.messages.queued', ['id' => $upload->id]));
    }
}
