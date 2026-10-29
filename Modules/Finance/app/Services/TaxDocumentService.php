<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Services\SequenceService;
use Modules\Core\Support\Money;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Finance\Jobs\GenerateTaxDocumentPdf;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Support\FinanceSettings;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\Pdf\AmountInWords;
use Modules\Order\Support\Pdf\PackingSlipView;
use Modules\Order\Support\Pdf\PdfRenderer;
use Modules\Returns\Models\ReturnRequest;
use Modules\Seller\Models\Seller;

/**
 * GST documents (FR-FIN-006, plan M17 §5.5):
 * - tax invoice (seller → buyer) when the seller order reaches ready_to_ship, numbered by
 *   M12 (SELLERCODE/FY/000001), same template as the M14 packing slip invoice;
 * - credit note for a refunded return: CN/{SELLERCODE}/{FY}/000001, referencing the invoice;
 * - monthly fee invoice (platform → seller) for commission and platform fees with their GST:
 *   FEE/{FY}/000001, skipped when nothing was charged (the default 0 % model).
 * PDFs are rendered by the GenerateTaxDocumentPdf job (lazily on first download when the
 * queue runs synchronously).
 */
class TaxDocumentService
{
    /**
     * Ledger types billed on the fee invoice (signed: reversals reduce them).
     */
    public const FEE_TYPES = [
        SellerLedgerEntry::TYPE_COMMISSION,
        SellerLedgerEntry::TYPE_COMMISSION_REVERSAL,
        SellerLedgerEntry::TYPE_PLATFORM_FEE,
        SellerLedgerEntry::TYPE_PLATFORM_FEE_REVERSAL,
    ];

    public function __construct(
        private readonly SequenceService $sequences,
        private readonly PdfRenderer $pdf,
        private readonly PackingSlipView $views,
    ) {}

    public function issueTaxInvoice(SellerOrder $sellerOrder): ?TaxDocument
    {
        $sellerOrder->loadMissing('items');

        if ($sellerOrder->invoice_no === null || $sellerOrder->isCancelled()) {
            return null;
        }

        $existing = TaxDocument::query()->where('number', $sellerOrder->invoice_no)->first();

        if ($existing !== null) {
            return $existing;
        }

        $totals = $this->itemTotals($sellerOrder->items->filter(fn (SalesOrderItem $item): bool => ! $item->isCancelled() && $item->quantity > $item->cancelled_qty)
            ->map(fn (SalesOrderItem $item): array => [$item, $item->quantity - $item->cancelled_qty])->values()->all());

        return $this->create([
            'type' => TaxDocument::TYPE_TAX_INVOICE,
            'number' => $sellerOrder->invoice_no,
            'seller_id' => $sellerOrder->seller_id,
            'seller_order_id' => $sellerOrder->id,
            'document_date' => $sellerOrder->invoice_date ?? Carbon::today(),
        ] + $totals);
    }

    public function issueCreditNote(ReturnRequest $return): ?TaxDocument
    {
        $return->loadMissing(['items.orderItem', 'sellerOrder', 'seller']);

        if ($return->type !== ReturnRequest::TYPE_RETURN || $return->seller === null) {
            return null;
        }

        $existing = TaxDocument::query()->where('return_request_id', $return->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = [];

        foreach ($return->items as $returnItem) {
            if ($returnItem->orderItem !== null) {
                $lines[] = [$returnItem->orderItem, min($returnItem->quantity, $returnItem->orderItem->quantity)];
            }
        }

        if ($lines === []) {
            return null;
        }

        $code = strtoupper((string) $return->seller->code);
        $fy = $this->sequences->financialYear();

        return DB::transaction(fn (): ?TaxDocument => $this->create([
            'type' => TaxDocument::TYPE_CREDIT_NOTE,
            'number' => $this->sequences->next("credit_note:{$code}:{$fy}", "CN/{$code}/{$fy}/"),
            'reference_number' => $return->sellerOrder?->invoice_no,
            'seller_id' => $return->seller_id,
            'seller_order_id' => $return->seller_order_id,
            'return_request_id' => $return->id,
            'document_date' => Carbon::today(),
        ] + $this->itemTotals($lines)));
    }

    /**
     * Fee invoices of a month ("2026-11") for every seller charged commission or fees in it.
     *
     * @return list<TaxDocument>
     */
    public function issueFeeInvoices(string $period): array
    {
        [$start, $end] = $this->periodRange($period);
        $sellerIds = SellerLedgerEntry::query()
            ->whereIn('type', self::FEE_TYPES)
            ->whereBetween('posted_at', [$start, $end])
            ->distinct()
            ->pluck('seller_id');
        $issued = [];

        foreach (Seller::query()->whereIn('id', $sellerIds)->orderBy('id')->get() as $seller) {
            $document = $this->issueFeeInvoice($seller, $period);

            if ($document !== null) {
                $issued[] = $document;
            }
        }

        return $issued;
    }

    public function issueFeeInvoice(Seller $seller, string $period): ?TaxDocument
    {
        $existing = TaxDocument::query()->where('seller_id', $seller->id)->where('type', TaxDocument::TYPE_FEE_INVOICE)->where('period', $period)->first();

        if ($existing !== null) {
            return $existing;
        }

        [$start, $end] = $this->periodRange($period);
        $entries = SellerLedgerEntry::query()
            ->forSeller($seller->id)
            ->whereIn('type', self::FEE_TYPES)
            ->whereBetween('posted_at', [$start, $end])
            ->get();

        $lines = [];

        foreach ([SellerLedgerEntry::TYPE_COMMISSION => SellerLedgerEntry::TYPE_COMMISSION_REVERSAL, SellerLedgerEntry::TYPE_PLATFORM_FEE => SellerLedgerEntry::TYPE_PLATFORM_FEE_REVERSAL] as $type => $reversal) {
            $taxable = Money::zero();
            $tax = Money::zero();

            foreach ($entries as $entry) {
                if ($entry->type === $type) {
                    $taxable = $taxable->plus($entry->amount);
                    $tax = $tax->plus($entry->tax_amount);
                } elseif ($entry->type === $reversal) {
                    $taxable = $taxable->minus($entry->amount);
                    $tax = $tax->minus($entry->tax_amount);
                }
            }

            if ($taxable->isPositive()) {
                $lines[] = ['code' => $type, 'taxable' => $taxable, 'tax' => $tax];
            }
        }

        if ($lines === []) {
            return null;
        }

        $platform = FinanceSettings::platform();
        $interState = $platform['state_id'] === null || $seller->state_id === null || $platform['state_id'] !== (int) $seller->state_id;
        $taxable = array_reduce($lines, fn (Money $carry, array $line): Money => $carry->plus($line['taxable']), Money::zero());
        $tax = array_reduce($lines, fn (Money $carry, array $line): Money => $carry->plus($line['tax']), Money::zero());
        ['cgst' => $cgst, 'sgst' => $sgst] = $interState ? ['cgst' => Money::zero(), 'sgst' => Money::zero()] : $tax->allocate(['cgst' => 1, 'sgst' => 1]);
        $fy = $this->sequences->financialYear(Carbon::parse($end));

        try {
            return DB::transaction(fn (): TaxDocument => $this->create([
                'type' => TaxDocument::TYPE_FEE_INVOICE,
                'number' => $this->sequences->next("fee_invoice:{$fy}", "FEE/{$fy}/"),
                'seller_id' => $seller->id,
                'period' => $period,
                'document_date' => Carbon::parse($end)->startOfDay(),
                'taxable_value' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $interState ? $tax : Money::zero(),
                'total' => $taxable->plus($tax),
                'lines' => array_map(fn (array $line): array => [
                    'code' => $line['code'],
                    'taxable' => $line['taxable']->toDecimal(),
                    'tax' => $line['tax']->toDecimal(),
                ], $lines),
            ]));
        } catch (UniqueConstraintViolationException) {
            return TaxDocument::query()->where('seller_id', $seller->id)->where('type', TaxDocument::TYPE_FEE_INVOICE)->where('period', $period)->first();
        }
    }

    /**
     * Render (or re-render) the PDF onto the documents disk.
     */
    public function render(TaxDocument $document): TaxDocument
    {
        $html = match ($document->type) {
            TaxDocument::TYPE_TAX_INVOICE => $this->renderTaxInvoice($document),
            TaxDocument::TYPE_CREDIT_NOTE => view('finance::pdf.credit-note', ['note' => $this->creditNoteView($document)])->render(),
            default => view('finance::pdf.fee-invoice', ['invoice' => $this->feeInvoiceView($document)])->render(),
        };

        $path = $this->folder($document->seller_id).'/'.$document->type.'/'.Str::slug(str_replace('/', '-', $document->number)).'.pdf';
        $this->disk()->put($path, $this->pdf->fromHtml($html));

        $document->forceFill(['path' => $path, 'generated_at' => Carbon::now()])->save();

        return $document;
    }

    /**
     * The PDF path for a download, rendered now when the queue has not done it yet.
     */
    public function pathFor(TaxDocument $document): string
    {
        if (! $document->isGenerated() || ! $this->disk()->exists((string) $document->path)) {
            $this->render($document);
        }

        return (string) $document->path;
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('finance.documents.disk', 'private'));
    }

    /**
     * @return array<string, mixed>
     */
    public function creditNoteView(TaxDocument $document): array
    {
        $document->loadMissing(['seller', 'sellerOrder.salesOrder.billingAddress', 'sellerOrder.salesOrder.shippingAddress', 'sellerOrder.warehouse']);
        $seller = $document->seller;
        $order = $document->sellerOrder?->salesOrder;
        $buyer = $order?->billingAddress ?? $order?->shippingAddress;

        return [
            'number' => $document->number,
            'reference' => $document->reference_number,
            'date' => $document->document_date,
            'supplier' => [
                'legal_name' => (string) ($seller?->gst_legal_name ?: $seller?->legal_name ?: $seller?->displayName()),
                'gstin' => $seller?->gstin,
                'address' => $document->sellerOrder?->warehouse?->fullAddress(),
            ],
            'buyer' => $buyer === null ? null : ['name' => $buyer->name, 'lines' => array_values(array_filter([$buyer->address_line1, $buyer->address_line2, $buyer->city_name, $buyer->state_name])), 'pincode' => $buyer->pincode],
            'orderNo' => $order?->order_no,
            'lines' => (array) $document->lines,
            'interState' => $document->igst->isPositive(),
            'document' => $document,
            'inWords' => AmountInWords::rupees($document->total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function feeInvoiceView(TaxDocument $document): array
    {
        $document->loadMissing('seller');
        $platform = FinanceSettings::platform();
        $seller = $document->seller;

        return [
            'number' => $document->number,
            'date' => $document->document_date,
            'period' => $document->period,
            'supplier' => $platform + ['state' => $this->stateName($platform['state_id'])],
            'recipient' => [
                'legal_name' => (string) ($seller?->gst_legal_name ?: $seller?->legal_name ?: $seller?->displayName()),
                'gstin' => $seller?->gstin,
                'code' => $seller?->code,
                'state' => $this->stateName($seller?->state_id),
            ],
            'lines' => (array) $document->lines,
            'interState' => $document->igst->isPositive(),
            'rate' => FinanceSettings::feeGstRate(),
            'document' => $document,
            'inWords' => AmountInWords::rupees($document->total),
        ];
    }

    /**
     * Totals and lines of (order item, units) pairs, prorated from the placement snapshot.
     *
     * @param  list<array{0: SalesOrderItem, 1: int}>  $pairs
     * @return array<string, mixed>
     */
    protected function itemTotals(array $pairs): array
    {
        $totals = ['taxable_value' => Money::zero(), 'cgst' => Money::zero(), 'sgst' => Money::zero(), 'igst' => Money::zero()];
        $lines = [];

        foreach ($pairs as [$item, $units]) {
            $line = [
                'taxable' => LedgerPosterImpl::prorate($item->taxable_value, $units, $item->quantity),
                'cgst' => LedgerPosterImpl::prorate($item->cgst, $units, $item->quantity),
                'sgst' => LedgerPosterImpl::prorate($item->sgst, $units, $item->quantity),
                'igst' => LedgerPosterImpl::prorate($item->igst, $units, $item->quantity),
            ];

            foreach (['taxable' => 'taxable_value', 'cgst' => 'cgst', 'sgst' => 'sgst', 'igst' => 'igst'] as $from => $to) {
                $totals[$to] = $totals[$to]->plus($line[$from]);
            }

            $lines[] = [
                'sales_order_item_id' => $item->id,
                'name' => $item->name,
                'variant' => $item->variant_label,
                'hsn' => $item->hsn_code,
                'qty' => $units,
                'gst_percent' => (string) $item->gst_percent,
                'taxable' => $line['taxable']->toDecimal(),
                'cgst' => $line['cgst']->toDecimal(),
                'sgst' => $line['sgst']->toDecimal(),
                'igst' => $line['igst']->toDecimal(),
                'total' => $line['taxable']->plus($line['cgst'])->plus($line['sgst'])->plus($line['igst'])->toDecimal(),
            ];
        }

        return $totals + [
            'total' => $totals['taxable_value']->plus($totals['cgst'])->plus($totals['sgst'])->plus($totals['igst']),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function create(array $attributes): TaxDocument
    {
        $document = TaxDocument::query()->create($attributes);

        // Rendered by a worker; on the sync queue (tests, single-process setups) the PDF is made
        // on first download instead of inside the request that issued the document.
        if (config('queue.default') !== 'sync') {
            GenerateTaxDocumentPdf::dispatch($document->id)->afterCommit();
        }

        return $document;
    }

    protected function renderTaxInvoice(TaxDocument $document): string
    {
        $sellerOrder = SellerOrder::query()->findOrFail($document->seller_order_id);

        return view('finance::pdf.tax-invoice', ['invoice' => $this->views->taxInvoice($sellerOrder)])->render();
    }

    /**
     * @return array{0: string, 1: string} first and last second of the month
     */
    protected function periodRange(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        return [$start->toDateTimeString(), $start->copy()->endOfMonth()->toDateTimeString()];
    }

    protected function stateName(?int $stateId): ?string
    {
        return $stateId === null ? null : DirectoryCountryState::query()->with('translations')->find($stateId)?->name;
    }

    protected function folder(int $sellerId): string
    {
        return trim((string) config('finance.documents.folder', 'finance-documents'), '/').'/'.$sellerId;
    }
}
