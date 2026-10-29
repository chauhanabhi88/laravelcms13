<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Seller;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Support\CsvExport;
use Modules\Finance\Support\FinanceOptions;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ledger statement (FR-FIN-005): the seller's entries with date, type and order filters and
 * the running balance (what the platform owes the seller after each entry), plus CSV export.
 * Every amount traces to its order item.
 */
class StatementController extends SellerPanelController
{
    public function index(Request $request): View
    {
        $sellerId = $this->seller()->id;
        $filters = $this->filters($request);

        $entries = $this->query($sellerId, $filters)
            ->with('sellerOrder')
            ->selectRaw('seller_ledger_entry.*, (SELECT SUM('.SellerLedgerEntry::signedSql('running').') FROM seller_ledger_entry running WHERE running.seller_id = seller_ledger_entry.seller_id AND running.id <= seller_ledger_entry.id) as running_balance')
            ->orderByDesc('id')
            ->paginate((int) config('finance.seller_per_page', 25))
            ->withQueryString();

        return view('finance::seller.payments.statement', [
            'entries' => $entries,
            'filters' => $filters,
            'types' => FinanceOptions::ledgerTypes(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($this->seller()->id, $this->filters($request))->with('sellerOrder')->orderBy('id');

        return CsvExport::stream('statement-'.now()->format('Ymd').'.csv', ['date', 'order', 'item_id', 'type', 'description', 'credit', 'debit', 'gst', 'status', 'payable_on', 'payout'], function () use ($query) {
            foreach ($query->lazyById(500) as $entry) {
                /** @var SellerLedgerEntry $entry */
                yield [
                    $entry->posted_at?->toDateString(),
                    $entry->sellerOrder?->sub_order_no,
                    $entry->sales_order_item_id,
                    $entry->type,
                    $entry->description,
                    $entry->isCredit() ? $entry->amount->toDecimal() : '',
                    $entry->isCredit() ? '' : $entry->amount->toDecimal(),
                    $entry->tax_amount->toDecimal(),
                    $entry->status,
                    $entry->eligible_on?->toDateString(),
                    $entry->payout_id,
                ];
            }
        });
    }

    /**
     * @return array{from: string|null, to: string|null, type: string|null, order: string|null}
     */
    private function filters(Request $request): array
    {
        $date = fn (string $key): ?string => rescue(fn () => $request->filled($key) ? Carbon::parse((string) $request->query($key))->toDateString() : null, null, false);
        $type = (string) $request->query('type', '');

        return [
            'from' => $date('from'),
            'to' => $date('to'),
            'type' => in_array($type, SellerLedgerEntry::TYPES, true) ? $type : null,
            'order' => $request->filled('order') ? mb_substr(trim((string) $request->query('order')), 0, 30) : null,
        ];
    }

    /**
     * @param  array{from: string|null, to: string|null, type: string|null, order: string|null}  $filters
     * @return Builder<SellerLedgerEntry>
     */
    private function query(int $sellerId, array $filters): Builder
    {
        return SellerLedgerEntry::query()
            ->forSeller($sellerId)
            ->when($filters['from'], fn (Builder $q, string $from) => $q->where('posted_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'], fn (Builder $q, string $to) => $q->where('posted_at', '<=', $to.' 23:59:59'))
            ->when($filters['type'], fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['order'], fn (Builder $q, string $order) => $q->whereHas('sellerOrder', fn (Builder $o) => $o->where('sub_order_no', 'like', '%'.$order.'%')));
    }
}
