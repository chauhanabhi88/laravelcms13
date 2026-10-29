<?php

declare(strict_types=1);

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Finance\Services\ResellerWalletService;
use Modules\Finance\Services\TaxDocumentService;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * finance:backfill-ledger: post the delivery entries, reseller margins and tax invoices of
 * seller orders handled before the Finance module existed (or whose listener failed). Safe to
 * repeat: every entry is keyed. --dry-run only counts.
 */
class BackfillLedgerCommand extends Command
{
    protected $signature = 'finance:backfill-ledger {--dry-run : Count the seller orders without posting}';

    protected $description = 'Post missing ledger entries, reseller margins and tax invoices for delivered / numbered seller orders.';

    public function handle(LedgerPosterImpl $ledger, ResellerWalletService $wallet, TaxDocumentService $documents): int
    {
        $delivered = SellerOrder::query()
            ->whereIn('status', SellerOrder::DELIVERED_STATUSES)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('seller_ledger_entry')
                ->whereColumn('seller_ledger_entry.seller_order_id', 'seller_order.id')
                ->where('seller_ledger_entry.type', 'sale'));
        $invoiced = SellerOrder::query()
            ->whereNotNull('invoice_no')
            ->where('status', '!=', SellerOrder::STATUS_CANCELLED)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('tax_document')->whereColumn('tax_document.number', 'seller_order.invoice_no'));

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d delivered seller order(s) without ledger entries, %d without a tax invoice.', $delivered->count(), $invoiced->count()));

            return self::SUCCESS;
        }

        $entries = 0;
        $invoices = 0;

        foreach ($delivered->lazyById(100) as $sellerOrder) {
            try {
                $entries += $ledger->postDelivered($sellerOrder);
                $wallet->creditMargins($sellerOrder);
            } catch (Throwable $e) {
                report($e);
                $this->warn($sellerOrder->sub_order_no.': '.$e->getMessage());
            }
        }

        foreach ($invoiced->lazyById(100) as $sellerOrder) {
            try {
                $invoices += (int) ($documents->issueTaxInvoice($sellerOrder) !== null);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info(sprintf('Posted %d ledger entries, issued %d tax invoice(s).', $entries, $invoices));

        return self::SUCCESS;
    }
}
