<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Services\TaxDocumentService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supplier invoices (FR-FIN-006): tax invoices per order, credit notes and the monthly fee
 * invoices, downloaded from the private disk after the tenancy check.
 */
class InvoiceController extends SellerPanelController
{
    public function __construct(private readonly TaxDocumentService $documents) {}

    public function index(Request $request): View
    {
        $type = (string) $request->query('type', TaxDocument::TYPE_TAX_INVOICE);
        $type = in_array($type, TaxDocument::TYPES, true) ? $type : TaxDocument::TYPE_TAX_INVOICE;

        return view('finance::seller.payments.invoices', [
            'type' => $type,
            'documents' => TaxDocument::query()->forSeller($this->seller()->id)->where('type', $type)->with('sellerOrder')
                ->orderByDesc('document_date')->orderByDesc('id')
                ->paginate((int) config('finance.seller_per_page', 25))->withQueryString(),
        ]);
    }

    public function download(Request $request): Response
    {
        $document = TaxDocument::query()->findOrFail((int) $request->route('document'));
        $this->authorizeOwned($document);

        return $this->documents->disk()->download($this->documents->pathFor($document), str_replace('/', '-', $document->number).'.pdf');
    }
}
