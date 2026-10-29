<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Report\Support\Grain;
use Modules\Report\Support\SalesAccumulator;

/**
 * Builds the daily_sales_stat rows of one day (plan M19 §5 "Aggregation"). compute() only
 * reads; aggregate() replaces the day's rows in one transaction, so re-running a day is
 * idempotent and grains that no longer have data disappear.
 */
class SalesAggregator
{
    /**
     * Ledger types that make up net revenue (commission + platform fees, amounts without GST).
     */
    public const REVENUE_TYPES = [
        SellerLedgerEntry::TYPE_COMMISSION,
        SellerLedgerEntry::TYPE_COMMISSION_REVERSAL,
        SellerLedgerEntry::TYPE_PLATFORM_FEE,
        SellerLedgerEntry::TYPE_PLATFORM_FEE_REVERSAL,
    ];

    private const CANCELLED_BY = [
        'customer' => 'cancelled_customer',
        'seller' => 'cancelled_seller',
    ];

    public function __construct(private readonly OrderFacts $facts) {}

    /**
     * The day's rows keyed by grain key ("seller|category|state|payment").
     *
     * @return array<string, array<string, int|string>>
     */
    public function compute(CarbonInterface $date): array
    {
        $from = CarbonImmutable::parse($date)->startOfDay();
        $to = $from->endOfDay();
        $accumulator = new SalesAccumulator;

        $this->placed($from, $to, $accumulator);
        $this->shipped($from, $to, $accumulator);
        $this->delivered($from, $to, $accumulator);

        return $accumulator->rows();
    }

    /**
     * Recompute the day and replace its stored rows. Returns the number of rows written.
     */
    public function aggregate(CarbonInterface $date): int
    {
        $day = CarbonImmutable::parse($date)->toDateString();
        $rows = $this->compute(CarbonImmutable::parse($day));
        $now = now();

        $records = [];
        foreach ($rows as $key => $metrics) {
            $records[] = ['date' => $day, ...Grain::split($key), ...$metrics, 'aggregated_at' => $now];
        }

        DB::transaction(function () use ($day, $records): void {
            DB::table('daily_sales_stat')->where('date', $day)->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                DB::table('daily_sales_stat')->insert($chunk);
            }
        });

        return count($records);
    }

    /**
     * Placement cohort: orders, units, GMV, discount, COD, new customers, cancellations, the
     * current status mix and net revenue.
     */
    private function placed(CarbonImmutable $from, CarbonImmutable $to, SalesAccumulator $accumulator): void
    {
        $rows = $this->facts->placedItems($from, $to)
            ->orderBy('i.id')
            ->select([
                'i.id', 'i.sales_order_id', 'i.seller_order_id', 'i.quantity', 'i.cancelled_qty', 'i.discount_allocated',
                'o.customer_id', 'o.payment_method', 'so.seller_id', 'so.status as seller_order_status', 'so.cancelled_by',
                'a.state_id', 'c.path',
                DB::raw(OrderFacts::LINE_PAISE.' as line_paise'),
            ])
            // A customer is new on the day of their first order that was not cancelled outright.
            ->selectRaw(
                'CASE WHEN EXISTS (SELECT 1 FROM sales_order prior WHERE prior.customer_id = o.customer_id AND prior.id < o.id AND prior.placed_at IS NOT NULL AND prior.status <> ?) THEN 0 ELSE 1 END as is_first_order',
                [SalesOrder::STATUS_CANCELLED],
            )
            ->get();

        /** @var array<int, list<string>> $itemKeys */
        $itemKeys = [];
        /** @var array<int, list<string>> $sellerOrderKeys */
        $sellerOrderKeys = [];

        foreach ($rows as $row) {
            $payment = Grain::paymentOf($row->payment_method);
            $category = Grain::rootCategory($row->path);
            $state = $row->state_id !== null ? (int) $row->state_id : null;
            $keys = Grain::keysFor((int) $row->seller_id, $category, $state, $payment);
            $itemKeys[(int) $row->id] = $keys;
            $sellerOrderKeys[(int) $row->seller_order_id] = Grain::keysFor((int) $row->seller_id, null, $state, $payment, false);

            $orderId = (int) $row->sales_order_id;
            $sellerOrderId = (int) $row->seller_order_id;
            $quantity = max(1, (int) $row->quantity);
            $line = (int) $row->line_paise;

            $accumulator->mark($keys, 'orders', $orderId);
            $accumulator->mark($keys, 'seller_orders', $sellerOrderId);
            $accumulator->add($keys, 'units', (int) $row->quantity);
            $accumulator->add($keys, 'gmv', $line);
            $accumulator->add($keys, 'discount', (int) round((float) $row->discount_allocated * 100));

            if ($payment === Grain::COD) {
                $accumulator->mark($keys, 'cod_orders', $orderId);
            }

            if ((int) $row->is_first_order === 1) {
                $accumulator->mark($keys, 'new_customers', (int) $row->customer_id);
            }

            if ((int) $row->cancelled_qty > 0) {
                $accumulator->add($keys, 'cancelled_gmv', intdiv($line * min($quantity, (int) $row->cancelled_qty), $quantity));
            }

            $this->markStatus($accumulator, $keys, $sellerOrderId, (string) $row->seller_order_status, $row->cancelled_by);
        }

        $this->revenue($itemKeys, $sellerOrderKeys, $accumulator);
    }

    /**
     * Current status bucket of a seller order placed in the period.
     *
     * @param  list<string>  $keys
     */
    private function markStatus(SalesAccumulator $accumulator, array $keys, int $sellerOrderId, string $status, ?string $cancelledBy): void
    {
        if ($status === SellerOrder::STATUS_CANCELLED) {
            $accumulator->mark($keys, 'cancelled', $sellerOrderId);
            $accumulator->mark($keys, self::CANCELLED_BY[$cancelledBy] ?? 'cancelled_platform', $sellerOrderId);

            return;
        }

        $bucket = match ($status) {
            SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED => 'rto_status',
            SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_RETURNED => 'returned_status',
            SellerOrder::STATUS_DELIVERED, SellerOrder::STATUS_COMPLETED => 'delivered_status',
            default => 'open',
        };

        $accumulator->mark($keys, $bucket, $sellerOrderId);
    }

    /**
     * Net revenue of the placed seller orders from the ledger: commission and platform fees
     * (debits of the seller) less their reversals, without GST. Entries tied to an item follow
     * its category; order-level entries count in every grain but the category ones.
     *
     * @param  array<int, list<string>>  $itemKeys
     * @param  array<int, list<string>>  $sellerOrderKeys
     */
    private function revenue(array $itemKeys, array $sellerOrderKeys, SalesAccumulator $accumulator): void
    {
        foreach (array_chunk(array_keys($sellerOrderKeys), 1000) as $ids) {
            $entries = DB::table('seller_ledger_entry')
                ->whereIn('seller_order_id', $ids)
                ->whereIn('type', self::REVENUE_TYPES)
                ->groupBy('seller_order_id', 'sales_order_item_id')
                ->get([
                    'seller_order_id', 'sales_order_item_id',
                    DB::raw("ROUND(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) * 100) as revenue_paise"),
                ]);

            foreach ($entries as $entry) {
                $keys = $entry->sales_order_item_id !== null && isset($itemKeys[(int) $entry->sales_order_item_id])
                    ? $itemKeys[(int) $entry->sales_order_item_id]
                    : $sellerOrderKeys[(int) $entry->seller_order_id];

                $accumulator->add($keys, 'net_revenue', (int) $entry->revenue_paise);
            }
        }
    }

    /**
     * Ship-date cohort: seller orders handed to the courier, and how many of them went back to
     * origin (RTO initiated or delivered back).
     */
    private function shipped(CarbonImmutable $from, CarbonImmutable $to, SalesAccumulator $accumulator): void
    {
        $rows = $this->facts->shippedItems($from, $to)
            ->get(['i.seller_order_id', 'so.seller_id', 'so.status as seller_order_status', 'o.payment_method', 'a.state_id', 'c.path']);

        foreach ($rows as $row) {
            $keys = $this->keysOf($row);
            $accumulator->mark($keys, 'shipped', (int) $row->seller_order_id);

            if (in_array($row->seller_order_status, [SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED], true)) {
                $accumulator->mark($keys, 'rto', (int) $row->seller_order_id);
            }
        }
    }

    /**
     * Delivery-date cohort: delivered seller orders and units, and the units since returned
     * (exchanges included) with the refunded value (exchanges excluded).
     */
    private function delivered(CarbonImmutable $from, CarbonImmutable $to, SalesAccumulator $accumulator): void
    {
        $rows = $this->facts->deliveredItems($from, $to)
            ->get([
                'i.seller_order_id', 'i.quantity', 'i.cancelled_qty', 'i.returned_qty', 'i.status as item_status',
                'so.seller_id', 'o.payment_method', 'a.state_id', 'c.path',
                DB::raw(OrderFacts::LINE_PAISE.' as line_paise'),
            ]);

        foreach ($rows as $row) {
            $keys = $this->keysOf($row);
            $quantity = max(1, (int) $row->quantity);
            $returned = min($quantity, (int) $row->returned_qty);

            $accumulator->mark($keys, 'delivered', (int) $row->seller_order_id);
            $accumulator->add($keys, 'delivered_units', max(0, (int) $row->quantity - (int) $row->cancelled_qty));
            $accumulator->add($keys, 'returned_units', $returned);

            if ($returned > 0 && $row->item_status !== SalesOrderItem::STATUS_EXCHANGED) {
                $accumulator->add($keys, 'return_value', intdiv((int) $row->line_paise * $returned, $quantity));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function keysOf(object $row): array
    {
        return Grain::keysFor(
            (int) $row->seller_id,
            Grain::rootCategory($row->path),
            $row->state_id !== null ? (int) $row->state_id : null,
            Grain::paymentOf($row->payment_method),
        );
    }
}
