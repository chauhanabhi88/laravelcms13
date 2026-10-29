<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\CodRemittance;
use Modules\Finance\Models\CodRemittanceLine;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;
use Throwable;

/**
 * COD remittance reconciliation (FR-FIN-008, plan M17 §5.7): the courier aggregator's
 * remittances (API import or CSV upload) are matched AWB by AWB to delivered COD shipments.
 * A matched line must carry the shipment's COD amount; once every delivered COD shipment of
 * an order is remitted, its payment moves collected → remitted. Unknown AWBs, amount
 * differences, non-COD or undelivered shipments and duplicates are flagged; delivered COD
 * shipments not remitted after cod_remittance_overdue_days days are listed as overdue.
 */
class CodRemittanceService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Import remittance rows: remittance_ref, remittance_date, utr, awb, amount. Rows are grouped
     * by remittance; a remittance already imported is skipped (idempotent).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<CodRemittance> the new remittances
     */
    public function import(array $rows, string $provider = 'shiprocket'): array
    {
        $groups = collect($rows)
            ->map(fn (array $row): array => array_change_key_case(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row)))
            ->filter(fn (array $row): bool => ($row['remittance_ref'] ?? '') !== '' && ($row['awb'] ?? '') !== '')
            ->groupBy('remittance_ref');

        $imported = [];

        foreach ($groups as $ref => $lines) {
            if (CodRemittance::query()->where('remittance_ref', (string) $ref)->exists()) {
                continue;
            }

            $imported[] = $this->importOne((string) $ref, $lines, $provider);
        }

        return $imported;
    }

    /**
     * Parse an uploaded CSV (header row: remittance_ref, remittance_date, utr, awb, amount).
     *
     * @return list<array<string, string>>
     *
     * @throws FinanceException
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new FinanceException(trans('finance::cod_remittance.messages.unreadable'), 'unreadable');
        }

        try {
            $header = array_map(fn ($cell): string => strtolower(trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF")), (array) fgetcsv($handle, escape: '\\'));
            $required = ['remittance_ref', 'remittance_date', 'awb', 'amount'];

            if (array_diff($required, $header) !== []) {
                throw new FinanceException(trans('finance::cod_remittance.messages.bad_header', ['columns' => implode(', ', $required)]), 'bad_header');
            }

            $rows = [];

            while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
                if ($cells === [null] || count(array_filter($cells, fn ($cell): bool => trim((string) $cell) !== '')) === 0) {
                    continue;
                }

                $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Delivered COD shipments with no matched remittance after the allowed days.
     *
     * @return Builder<Shipment>
     */
    public function overdueQuery(?Carbon $now = null): Builder
    {
        $days = max(1, (int) config('finance.cod_remittance_overdue_days', 10));

        return Shipment::query()
            ->where('direction', Shipment::DIRECTION_FORWARD)
            ->where('payment_mode', 'cod')
            ->where('status', Shipment::STATUS_DELIVERED)
            ->where('delivered_at', '<', ($now ?? Carbon::now())->copy()->subDays($days))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('cod_remittance_line')
                ->whereColumn('cod_remittance_line.shipment_id', 'shipment.id')
                ->where('cod_remittance_line.matched', config('core.yes')));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    protected function importOne(string $ref, Collection $lines, string $provider): CodRemittance
    {
        $remittance = DB::transaction(function () use ($ref, $lines, $provider): CodRemittance {
            $first = (array) $lines->first();
            $total = $lines->reduce(fn (Money $carry, array $row): Money => $carry->plus($this->money($row['amount'] ?? '0')), Money::zero());

            $remittance = CodRemittance::query()->create([
                'provider' => $provider,
                'remittance_ref' => mb_substr($ref, 0, 60),
                'remittance_date' => $this->date($first['remittance_date'] ?? null),
                'amount' => $total,
                'utr' => isset($first['utr']) && $first['utr'] !== '' ? mb_substr((string) $first['utr'], 0, 40) : null,
                'status' => CodRemittance::STATUS_RECEIVED,
                'raw' => ['rows' => $lines->values()->all()],
            ]);

            $mismatches = 0;
            $seen = [];

            foreach ($lines as $row) {
                $awb = mb_substr((string) $row['awb'], 0, 40);
                $line = $this->match($remittance, $awb, $this->money($row['amount'] ?? '0'), isset($seen[$awb]));
                $seen[$awb] = true;
                $mismatches += $line->isMatched() ? 0 : 1;
            }

            $remittance->forceFill([
                'lines_count' => $lines->count(),
                'mismatch_count' => $mismatches,
                'status' => $mismatches === 0 ? CodRemittance::STATUS_RECONCILED : CodRemittance::STATUS_MISMATCH,
            ])->save();

            return $remittance;
        });

        $this->markRemittedPayments($remittance);

        return $remittance;
    }

    protected function match(CodRemittance $remittance, string $awb, Money $amount, bool $repeatedInFile): CodRemittanceLine
    {
        $shipment = Shipment::query()->where('awb', $awb)->first();
        $issue = null;
        $expected = null;

        if ($shipment === null) {
            $issue = CodRemittanceLine::ISSUE_UNKNOWN_AWB;
        } else {
            $expected = $shipment->cod_amount;
            $alreadyMatched = CodRemittanceLine::query()->where('shipment_id', $shipment->id)->where('matched', config('core.yes'))->exists();

            $issue = match (true) {
                $repeatedInFile || $alreadyMatched => CodRemittanceLine::ISSUE_DUPLICATE,
                $shipment->payment_mode !== 'cod' => CodRemittanceLine::ISSUE_NOT_COD,
                $shipment->status !== Shipment::STATUS_DELIVERED => CodRemittanceLine::ISSUE_NOT_DELIVERED,
                ! $amount->equals($shipment->cod_amount) => CodRemittanceLine::ISSUE_AMOUNT_MISMATCH,
                default => null,
            };
        }

        return CodRemittanceLine::query()->create([
            'cod_remittance_id' => $remittance->id,
            'awb' => $awb,
            'shipment_id' => $shipment?->id,
            'amount' => $amount,
            'expected_amount' => $expected,
            'matched' => $issue === null ? config('core.yes') : config('core.no'),
            'issue' => $issue,
        ]);
    }

    /**
     * Payments whose delivered COD shipments are all remitted: collected → remitted.
     */
    protected function markRemittedPayments(CodRemittance $remittance): void
    {
        $orderIds = SellerOrder::query()
            ->whereIn('id', Shipment::query()->whereIn('id', CodRemittanceLine::query()->where('cod_remittance_id', $remittance->id)->where('matched', config('core.yes'))->select('shipment_id'))->select('seller_order_id'))
            ->distinct()
            ->pluck('sales_order_id');

        foreach ($orderIds as $orderId) {
            $open = Shipment::query()
                ->where('direction', Shipment::DIRECTION_FORWARD)
                ->where('payment_mode', 'cod')
                ->where('status', Shipment::STATUS_DELIVERED)
                ->whereIn('seller_order_id', SellerOrder::query()->where('sales_order_id', $orderId)->select('id'))
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('cod_remittance_line')
                    ->whereColumn('cod_remittance_line.shipment_id', 'shipment.id')
                    ->where('cod_remittance_line.matched', config('core.yes')))
                ->exists();

            if ($open) {
                continue;
            }

            $payment = Payment::query()->where('sales_order_id', $orderId)->where('method', 'cod')->where('status', Payment::STATUS_COLLECTED)->first();

            if ($payment !== null) {
                try {
                    $this->payments->markCodRemitted($payment);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    protected function money(mixed $value): Money
    {
        $value = is_string($value) ? str_replace([',', '₹', ' '], '', $value) : $value;

        return is_numeric($value) ? Money::of((string) $value) : Money::zero();
    }

    protected function date(mixed $value): Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value)->startOfDay() : Carbon::today();
        } catch (Throwable) {
            return Carbon::today();
        }
    }
}
