<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Support\Money;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Models\LedgerAdjustment;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Services\LedgerAdjustmentService;
use Modules\Finance\Support\CsvExport;
use Modules\Finance\Support\FinanceOptions;
use Modules\Seller\Models\Seller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ledger search (FR-FIN-008) across sellers, CSV export, and manual adjustments: one admin
 * requests, another approves (maker-checker) before the entry is posted.
 */
class LedgerController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'ledger';

    protected const VIEW = 'ledger';

    public function __construct(private readonly LedgerAdjustmentService $adjustments)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request, [
            'pendingAdjustments' => LedgerAdjustment::query()->where('status', LedgerAdjustment::STATUS_PENDING)->with(['seller', 'requester'])->latest('id')->limit(50)->get(),
            'directionOptions' => FinanceOptions::directions(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->gridRepository()->filter($request)->with(['seller', 'sellerOrder'])->orderBy('id');

        return CsvExport::stream('seller-ledger-'.now()->format('Ymd-His').'.csv', [
            'id', 'posted_at', 'seller_code', 'seller', 'order', 'type', 'direction', 'amount', 'gst', 'status', 'eligible_on', 'payout_id', 'description',
        ], function () use ($query) {
            foreach ($query->lazyById(500) as $entry) {
                /** @var SellerLedgerEntry $entry */
                yield [
                    $entry->id,
                    $entry->posted_at?->toDateTimeString(),
                    $entry->seller?->code,
                    $entry->seller?->displayName(),
                    $entry->sellerOrder?->sub_order_no,
                    $entry->type,
                    $entry->direction,
                    $entry->amount->toDecimal(),
                    $entry->tax_amount->toDecimal(),
                    $entry->status,
                    $entry->eligible_on?->toDateString(),
                    $entry->payout_id,
                    $entry->description,
                ];
            }
        });
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_code' => ['required', 'string', 'max:12'],
            'direction' => ['required', Rule::in([SellerLedgerEntry::CREDIT, SellerLedgerEntry::DEBIT])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $seller = Seller::query()->where('code', strtoupper(trim($validated['seller_code'])))->first();

        if ($seller === null) {
            return redirect()->back()->withInput()->withErrors(['seller_code' => trans('finance::ledger.messages.seller_not_found')]);
        }

        try {
            $this->adjustments->request($seller, $validated['direction'], Money::of((string) $validated['amount']), $validated['reason'], $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.ledger.index', updateUrlParams())->with('success', trans('finance::ledger.messages.adjustment_requested'));
    }

    public function approveAdjustment(Request $request): RedirectResponse
    {
        return $this->decide($request, true);
    }

    public function rejectAdjustment(Request $request): RedirectResponse
    {
        return $this->decide($request, false);
    }

    private function decide(Request $request, bool $approve): RedirectResponse
    {
        try {
            $adjustment = LedgerAdjustment::query()->findOrFail((int) $request->route('id'));
            $approve ? $this->adjustments->approve($adjustment, $request->user()) : $this->adjustments->reject($adjustment, $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.ledger.index', updateUrlParams())->with('success', trans('finance::ledger.messages.adjustment_'.($approve ? 'approved' : 'rejected')));
    }
}
