<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Support\FinanceSettings;
use Modules\Order\Models\SellerOrder;

/**
 * A seller's money at a glance (plan M17 §3.3, §5.4): net amounts (credits − debits, GST on
 * fees included) per ledger status, the next payout and what was paid recently.
 *
 * @phpstan-type Balances array{pending: Money, eligible: Money, on_hold: Money, settled: Money, in_payout: Money}
 */
class SellerBalanceService
{
    /**
     * pending: still in the return window / payout delay; eligible: payable in the next batch;
     * on_hold: payable but held; in_payout: batched, not paid yet; settled: paid out in total.
     *
     * @return Balances
     */
    public function balances(int $sellerId): array
    {
        $rows = SellerLedgerEntry::query()
            ->forSeller($sellerId)
            ->where('type', '!=', SellerLedgerEntry::TYPE_PAYOUT)
            ->selectRaw('status, (payout_id IS NOT NULL) as batched, SUM('.SellerLedgerEntry::signedSql().') as total')
            ->groupBy('status', DB::raw('(payout_id IS NOT NULL)'))
            ->get();

        $sum = fn (string $status, ?bool $batched = null): Money => $rows
            ->filter(fn ($row): bool => $row->status === $status && ($batched === null || (bool) $row->batched === $batched))
            ->reduce(fn (Money $carry, $row): Money => $carry->plus(Money::of((string) ($row->total ?? '0'))), Money::zero());

        $paid = SellerLedgerEntry::query()->forSeller($sellerId)->where('type', SellerLedgerEntry::TYPE_PAYOUT)->sum('amount');

        return [
            'pending' => $sum(SellerLedgerEntry::STATUS_PENDING),
            'eligible' => $sum(SellerLedgerEntry::STATUS_ELIGIBLE, false),
            'on_hold' => $sum(SellerLedgerEntry::STATUS_ON_HOLD),
            'in_payout' => $sum(SellerLedgerEntry::STATUS_ELIGIBLE, true),
            'settled' => Money::of((string) $paid),
        ];
    }

    /**
     * Paid out in the last $days days.
     */
    public function paidSince(int $sellerId, int $days = 30): Money
    {
        $total = SellerPayout::query()
            ->forSeller($sellerId)
            ->where('status', SellerPayout::STATUS_PAID)
            ->where('paid_at', '>=', Carbon::now()->subDays($days))
            ->sum('amount');

        return Money::of((string) $total);
    }

    /**
     * Date of the next scheduled payout batch (today when today is the payout day).
     */
    public function nextPayoutDate(?Carbon $from = null): Carbon
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $day = FinanceSettings::payoutDay();

        return strtolower($from->englishDayOfWeek) === $day ? $from : $from->next($day);
    }

    /**
     * Weekly payout totals for the last $weeks weeks (oldest first), for the overview chart.
     *
     * @return list<array{week: string, amount: Money}>
     */
    public function weeklyPayouts(int $sellerId, int $weeks = 8): array
    {
        $start = Carbon::today()->startOfWeek()->subWeeks($weeks - 1);
        $payouts = SellerPayout::query()
            ->forSeller($sellerId)
            ->where('status', SellerPayout::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at']);

        $series = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $amount = $payouts
                ->filter(fn (SellerPayout $payout): bool => $payout->paid_at !== null && $payout->paid_at->between($weekStart, $weekEnd))
                ->reduce(fn (Money $carry, SellerPayout $payout): Money => $carry->plus($payout->amount), Money::zero());
            $series[] = ['week' => $weekStart->format('d M'), 'amount' => $amount];
        }

        return $series;
    }

    /**
     * Settlement breakdown of one seller order (plan M17 §5.4): sale, commission, fees, TCS,
     * TDS, logistics, returns and the net, with the payout status of its entries.
     *
     * @return array{lines: array<string, Money>, net: Money, status: string, entries: Collection<int, SellerLedgerEntry>}
     */
    public function orderSettlement(SellerOrder $sellerOrder): array
    {
        $entries = SellerLedgerEntry::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->with('payout')
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($entries as $entry) {
            $lines[$entry->type] = ($lines[$entry->type] ?? Money::zero())->plus($entry->signedValue());
        }

        $net = array_reduce($lines, fn (Money $carry, Money $line): Money => $carry->plus($line), Money::zero());
        $statuses = $entries->pluck('status')->unique()->values()->all();
        $status = match (true) {
            $entries->isEmpty() => 'not_posted',
            $statuses === [SellerLedgerEntry::STATUS_SETTLED] => SellerLedgerEntry::STATUS_SETTLED,
            in_array(SellerLedgerEntry::STATUS_ON_HOLD, $statuses, true) => SellerLedgerEntry::STATUS_ON_HOLD,
            in_array(SellerLedgerEntry::STATUS_PENDING, $statuses, true) => SellerLedgerEntry::STATUS_PENDING,
            default => SellerLedgerEntry::STATUS_ELIGIBLE,
        };

        return ['lines' => $lines, 'net' => $net, 'status' => $status, 'entries' => $entries];
    }
}
