<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Services\TaxDocumentService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tax invoices, credit notes and fee invoices (FR-FIN-006): list, download, re-render the
 * PDF, and issue a month's fee invoices on demand.
 */
class TaxDocumentController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'tax_document';

    protected const VIEW = 'tax_document';

    public function __construct(private readonly TaxDocumentService $documents)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request, ['lastMonth' => Carbon::now()->subMonthNoOverflow()->format('Y-m')]);
    }

    public function download(Request $request): Response
    {
        $document = TaxDocument::query()->findOrFail((int) $request->route('id'));

        return $this->documents->disk()->download($this->documents->pathFor($document), basename(str_replace('/', '-', $document->number)).'.pdf');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $document = TaxDocument::query()->findOrFail((int) $request->route('id'));
        $this->documents->render($document);

        return redirect()->back()->with('success', trans('finance::tax_document.messages.regenerated', ['number' => $document->number]));
    }

    public function feeInvoices(Request $request): RedirectResponse
    {
        $validated = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $issued = $this->documents->issueFeeInvoices($validated['period']);

        return redirect()->route('admin.tax_document.index', updateUrlParams())->with('success', trans('finance::tax_document.messages.fee_invoices', ['count' => count($issued), 'period' => $validated['period']]));
    }
}
