<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Models\CodRemittance;
use Modules\Finance\Services\CodRemittanceService;

/**
 * COD remittance reconciliation (FR-FIN-008, plan M17 §5.7): imported / uploaded remittances,
 * their AWB matches and mismatches, and delivered COD shipments overdue for remittance.
 */
class CodRemittanceController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'cod_remittance';

    protected const VIEW = 'cod_remittance';

    public function __construct(private readonly CodRemittanceService $remittances)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request, [
            'overdue' => $this->remittances->overdueQuery()->with('sellerOrder')->orderBy('delivered_at')->limit(50)->get(),
            'overdueCount' => $this->remittances->overdueQuery()->count(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            $imported = $this->remittances->import($this->remittances->parseCsv($request->file('file')->getRealPath()), 'upload');
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.cod_remittance.index', updateUrlParams())
            ->with('success', trans('finance::cod_remittance.messages.imported', ['count' => count($imported)]));
    }

    public function show(Request $request): View|RedirectResponse
    {
        $remittance = $this->gridRepository()->find((int) $request->route('id'));

        if (! $remittance instanceof CodRemittance) {
            return redirect()->route('admin.cod_remittance.index', updateUrlParams())->with('error', trans('finance::finance.messages.not_found'));
        }

        return view('finance::backend.cod_remittance.show', ['remittance' => $remittance->load('lines.shipment.sellerOrder')]);
    }
}
