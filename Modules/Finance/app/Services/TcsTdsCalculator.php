<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Support\FinanceSettings;
use Modules\Seller\Models\Seller;

/**
 * TCS / TDS (plan M17 §5.6). TCS (CGST Act §52) is collected on the net taxable supplies of
 * GST-registered sellers (taxable value of delivered items − returns): tcs_rate %, split
 * 0.5 % CGST + 0.5 % SGST for intra-state supplies or 1 % IGST inter-state. TDS (§194-O) is
 * tds_rate % of the gross amount (tds_rate_no_pan % without PAN).
 *
 * The monthly report (GSTR-8 support) is built from the ledger's TCS entries, so it always
 * reconciles with what sellers were charged: gross = Σ base of tcs entries, returns = Σ base
 * of tcs_reversal entries, by posting month.
 *
 * @phpstan-type TcsRow array{seller_id: int, seller: string, code: string, gstin: string|null, gross: Money, returns: Money, net: Money, tcs: Money, cgst: Money, sgst: Money, igst: Money, computed: Money}
 */
class TcsTdsCalculator
{
    public function tcs(Money $taxable, bool $gstRegistered): Money
    {
        return $gstRegistered ? $taxable->percent(FinanceSettings::tcsRate()) : Money::zero();
    }

    public function tds(Money $gross, bool $hasPan): Money
    {
        return $gross->percent($hasPan ? FinanceSettings::tdsRate() : FinanceSettings::tdsRateWithoutPan());
    }

    /**
     * One row per seller with TCS activity in the month ("2026-11").
     *
     * @return list<TcsRow>
     */
    public function monthlyReport(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $entries = SellerLedgerEntry::query()
            ->whereIn('type', [SellerLedgerEntry::TYPE_TCS, SellerLedgerEntry::TYPE_TCS_REVERSAL])
            ->whereBetween('posted_at', [$start, $start->copy()->endOfMonth()])
            ->orderBy('seller_id')
            ->get(['seller_id', 'type', 'amount', 'base_amount', 'meta']);

        if ($entries->isEmpty()) {
            return [];
        }

        $sellers = Seller::query()->withTrashed()->whereIn('id', $entries->pluck('seller_id')->unique())->get()->keyBy('id');
        $rate = FinanceSettings::tcsRate();
        $rows = [];

        foreach ($entries->groupBy('seller_id') as $sellerId => $sellerEntries) {
            $row = ['gross' => Money::zero(), 'returns' => Money::zero(), 'tcs' => Money::zero(), 'inter' => Money::zero(), 'intra' => Money::zero()];

            foreach ($sellerEntries as $entry) {
                /** @var SellerLedgerEntry $entry */
                $sign = $entry->type === SellerLedgerEntry::TYPE_TCS ? 1 : -1;
                $base = $entry->base_amount ?? Money::zero();
                $row[$sign > 0 ? 'gross' : 'returns'] = $row[$sign > 0 ? 'gross' : 'returns']->plus($base);
                $signed = $sign > 0 ? $entry->amount : Money::zero()->minus($entry->amount);
                $row['tcs'] = $row['tcs']->plus($signed);
                $bucket = ! empty($entry->meta['inter_state']) ? 'inter' : 'intra';
                $row[$bucket] = $row[$bucket]->plus($signed);
            }

            ['cgst' => $cgst, 'sgst' => $sgst] = $row['intra']->allocate(['cgst' => 1, 'sgst' => 1]);
            $seller = $sellers->get($sellerId);
            $net = $row['gross']->minus($row['returns']);

            $rows[] = [
                'seller_id' => (int) $sellerId,
                'seller' => (string) $seller?->displayName(),
                'code' => (string) $seller?->code,
                'gstin' => $seller?->gstin,
                'gross' => $row['gross'],
                'returns' => $row['returns'],
                'net' => $net,
                'tcs' => $row['tcs'],
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $row['inter'],
                'computed' => $net->percent($rate),
            ];
        }

        return $rows;
    }
}
