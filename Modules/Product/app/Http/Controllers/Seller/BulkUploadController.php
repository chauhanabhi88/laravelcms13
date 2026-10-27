<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Exports\CategoryTemplateExport;
use Modules\Product\Http\Requests\Seller\BulkUploadRequest;
use Modules\Product\Imports\PriceStockImport;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Product\Services\CatalogUploadService;
use Modules\Product\Support\Import\CatalogTemplate;
use Modules\Product\Support\Listing\CategoryPicker;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk catalog upload (FR-SCT-003/009): category template download, upload, history with
 * status, row errors and the error report.
 */
class BulkUploadController extends SellerPanelController
{
    public function __construct(
        private readonly SellerCatalogRepository $catalog,
        private readonly CatalogUploadService $uploads,
        private readonly CategoryPicker $categories,
    ) {}

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), [CatalogUpload::TYPE_NEW_CATALOG, CatalogUpload::TYPE_PRICE_STOCK], true) ? (string) $request->query('type') : null;

        return view('product::seller.upload.index', [
            'sellerStoreName' => $this->seller()->displayName(),
            'uploads' => $this->catalog->uploads($this->seller()->id, $type, (int) config('product.seller_per_page', 20)),
            'type' => $type,
            'categoryLabels' => $this->categories->options(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('product::seller.upload.create', [
            'sellerStoreName' => $this->seller()->displayName(),
            'categoryOptions' => $this->categories->options(),
            'selectedCategory' => (int) $request->query('category_id') ?: null,
            'maxRows' => (int) config('product.bulk_max_rows', 5000),
            'maxMb' => (int) config('product.bulk_max_mb', 10),
        ]);
    }

    public function template(Request $request): BinaryFileResponse|RedirectResponse
    {
        $categoryId = (int) $request->query('category_id');

        if (! $this->categories->isListable($categoryId)) {
            return redirect()->to(frontRoute('seller.upload.create'))->with('error', trans('product::seller.wizard.category.invalid'));
        }

        $category = CatalogCategory::query()->findOrFail($categoryId);
        $name = 'catalog-template-'.(Str::slug($this->categories->breadcrumb($categoryId)) ?: $categoryId).'.xlsx';

        return Excel::download(new CategoryTemplateExport(CatalogTemplate::for($category)), $name);
    }

    public function store(BulkUploadRequest $request): RedirectResponse
    {
        $categoryId = (int) $request->input('catalog_category_id');

        if (! $this->categories->isListable($categoryId)) {
            return back()->withInput()->withErrors(['catalog_category_id' => trans('product::seller.wizard.category.invalid')]);
        }

        $upload = $this->uploads->createNewCatalog($this->seller(), $this->sellerUser(), $categoryId, $request->file('file'), $request->boolean('save_as_draft'));

        return redirect()->to(frontRoute('seller.upload.show', [$upload->id]))->with('success', trans('product::seller.uploads.messages.queued', ['id' => $upload->id]));
    }

    public function show(Request $request): View
    {
        $upload = $this->ownedUpload($request);
        $upload->load(['rowErrors', 'uploader']);

        return view('product::seller.upload.show', [
            'sellerStoreName' => $this->seller()->displayName(),
            'upload' => $upload,
            'categoryLabel' => $upload->catalog_category_id !== null ? $this->categories->breadcrumb($upload->catalog_category_id) : null,
            'errorsByRow' => $upload->rowErrors->groupBy('row_number'),
            'columnLabels' => $this->columnLabels($upload),
        ]);
    }

    /**
     * Progress for the history / detail page while an upload is queued or processing.
     */
    public function status(Request $request): JsonResponse
    {
        $upload = $this->ownedUpload($request);

        return response()->json([
            'status' => $upload->status,
            'label' => $upload->statusLabel(),
            'finished' => $upload->isFinished(),
            'total_rows' => $upload->total_rows,
            'success_rows' => $upload->success_rows,
            'failed_rows' => $upload->failed_rows,
        ]);
    }

    public function errorReport(Request $request): StreamedResponse
    {
        $upload = $this->ownedUpload($request);
        abort_if($upload->error_report_path === null || ! Storage::disk($this->uploads->disk())->exists($upload->error_report_path), 404);

        return Storage::disk($this->uploads->disk())->download($upload->error_report_path, 'upload-'.$upload->id.'-errors.xlsx');
    }

    public function file(Request $request): StreamedResponse
    {
        $upload = $this->ownedUpload($request);
        abort_unless(Storage::disk($this->uploads->disk())->exists($upload->file_path), 404);

        return Storage::disk($this->uploads->disk())->download($upload->file_path, $upload->original_name);
    }

    /**
     * Column key => heading as the seller's template names it.
     *
     * @return array<string, string>
     */
    private function columnLabels(CatalogUpload $upload): array
    {
        $labels = [];

        foreach (PriceStockImport::COLUMNS as $column) {
            $labels[$column] = trans('product::seller.template.columns.'.$column);
        }

        $category = $upload->catalog_category_id !== null ? CatalogCategory::query()->find($upload->catalog_category_id) : null;

        if ($category !== null) {
            foreach (CatalogTemplate::for($category)->columns() as $column) {
                $labels[$column['key']] = $column['heading'];
            }
        }

        return $labels;
    }

    private function ownedUpload(Request $request): CatalogUpload
    {
        $upload = CatalogUpload::query()->findOrFail((int) $request->route('upload'));
        $this->authorizeOwned($upload);

        return $upload;
    }
}
