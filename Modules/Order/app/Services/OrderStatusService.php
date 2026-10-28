<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Core\Services\SequenceService;
use Modules\Order\Events\OrderCompleted;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;

/**
 * Order state (FR-ORD-005, plan M12 §5.4). Seller orders are authoritative and change only
 * through transition(); after each change the sales order's customer-level status is derived
 * again by refresh(). Logistics (M13), fulfilment (M14) and returns (M15) call transition().
 */
class OrderStatusService
{
    /**
     * Timestamp column set when a seller order enters the status.
     */
    private const TIMESTAMPS = [
        SellerOrder::STATUS_READY_TO_SHIP => 'accepted_at',
        SellerOrder::STATUS_PACKED => 'packed_at',
        SellerOrder::STATUS_SHIPPED => 'shipped_at',
        SellerOrder::STATUS_DELIVERED => 'delivered_at',
        SellerOrder::STATUS_CANCELLED => 'cancelled_at',
    ];

    public function __construct(private readonly SequenceService $sequences) {}

    /**
     * Move a seller order to $to (locked, with history and SellerOrderStatusChanged), then
     * refresh its sales order. Entering ready_to_ship assigns the invoice number.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws InvalidStateTransition
     */
    public function transition(SellerOrder $sellerOrder, string $to, ?string $reason = null, ?Model $actor = null, ?string $actorType = null, array $meta = []): SellerOrder
    {
        DB::transaction(function () use ($sellerOrder, $to, $reason, $actor, $actorType, $meta): void {
            $locked = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            if ($locked->status === $to) {
                return;
            }

            $this->apply($locked, $to, $reason, $actor, $actorType, $meta);
            $this->refresh($locked->salesOrder()->firstOrFail());
        });

        return $sellerOrder->refresh();
    }

    /**
     * The transition itself, for callers already holding the seller order's lock in their
     * own transaction (cancellation, confirmation). Does not refresh the sales order.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws InvalidStateTransition
     */
    public function apply(SellerOrder $sellerOrder, string $to, ?string $reason = null, ?Model $actor = null, ?string $actorType = null, array $meta = []): void
    {
        $column = self::TIMESTAMPS[$to] ?? null;

        if ($column !== null && $sellerOrder->getAttribute($column) === null) {
            $sellerOrder->setAttribute($column, Carbon::now());
        }

        if ($to === SellerOrder::STATUS_READY_TO_SHIP) {
            $this->assignInvoiceNumber($sellerOrder, false);
        }

        $sellerOrder->transitionTo($to, $reason, $meta, $actor, $actorType);
    }

    /**
     * Release an order for fulfilment: every seller order still in pending_payment moves to
     * pending_acceptance and the order becomes confirmed (OrderConfirmed). Idempotent: false
     * when nothing was waiting (already confirmed, or cancelled).
     */
    public function confirm(SalesOrder $order, ?string $reason = null): bool
    {
        $confirmed = DB::transaction(function () use ($order, $reason): bool {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === SalesOrder::STATUS_CANCELLED) {
                return false;
            }

            $waiting = SellerOrder::query()
                ->where('sales_order_id', $locked->id)
                ->where('status', SellerOrder::STATUS_PENDING_PAYMENT)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($waiting->isEmpty()) {
                return false;
            }

            foreach ($waiting as $sellerOrder) {
                $this->apply($sellerOrder, SellerOrder::STATUS_PENDING_ACCEPTANCE, $reason);
            }

            $this->refresh($locked);
            OrderConfirmed::dispatch($locked);

            return true;
        });

        $order->refresh();

        return $confirmed;
    }

    /**
     * A COD order waiting for the buyer's OTP: its seller orders stay in pending_payment.
     */
    public function markAwaitingCodOtp(SalesOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== SalesOrder::STATUS_PENDING_COD_VERIFICATION && $locked->isAwaitingPayment()) {
                $locked->transitionTo(SalesOrder::STATUS_PENDING_COD_VERIFICATION);
            }
        });

        $order->refresh();
    }

    /**
     * Derive the customer-level status from the seller orders (and item cancellations) and
     * store it with a history row when it changed. Fires OrderCompleted on completion.
     */
    public function refresh(SalesOrder $order): string
    {
        $statuses = SellerOrder::query()->where('sales_order_id', $order->id)->pluck('status')->all();
        $hasCancelledItems = SalesOrderItem::query()
            ->where('sales_order_id', $order->id)
            ->where('status', SalesOrderItem::STATUS_CANCELLED)
            ->exists();

        $status = self::derive($statuses, $order->status, $hasCancelledItems);

        if ($status === $order->status) {
            return $status;
        }

        if ($status === SalesOrder::STATUS_CANCELLED && $order->cancelled_at === null) {
            $order->cancelled_at = Carbon::now();
        }

        if ($status === SalesOrder::STATUS_COMPLETED && $order->completed_at === null) {
            $order->completed_at = Carbon::now();
        }

        $order->transitionTo($status);

        if ($status === SalesOrder::STATUS_COMPLETED) {
            OrderCompleted::dispatch($order);
        }

        return $status;
    }

    /**
     * The customer-level status (plan M12 §5.4) of seller orders in $statuses:
     * - all cancelled (or returned to origin) → cancelled
     * - all still waiting for payment → the current awaiting status (pending_payment by default)
     * - all completed → completed; all delivered (incl. returns) → delivered
     * - some on the way / delivered: partially_shipped while others are not dispatched, else shipped
     * - all before dispatch: partially_cancelled when a seller order or item was cancelled, else confirmed
     *
     * @param  list<string>  $statuses
     */
    public static function derive(array $statuses, string $current, bool $hasCancelledItems = false): string
    {
        if ($statuses === []) {
            return $current;
        }

        $closed = [SellerOrder::STATUS_CANCELLED, SellerOrder::STATUS_RTO_DELIVERED];
        $active = array_values(array_filter($statuses, fn (string $status): bool => ! in_array($status, $closed, true)));

        if ($active === []) {
            return SalesOrder::STATUS_CANCELLED;
        }

        $all = fn (array $set): bool => array_diff($active, $set) === [];
        $any = fn (array $set): bool => array_intersect($active, $set) !== [];

        if ($all([SellerOrder::STATUS_PENDING_PAYMENT])) {
            return in_array($current, SalesOrder::AWAITING_PAYMENT_STATUSES, true) && $current !== SalesOrder::STATUS_PLACED
                ? $current
                : SalesOrder::STATUS_PENDING_PAYMENT;
        }

        if ($all([SellerOrder::STATUS_COMPLETED])) {
            return SalesOrder::STATUS_COMPLETED;
        }

        if ($all(SellerOrder::DELIVERED_STATUSES)) {
            return SalesOrder::STATUS_DELIVERED;
        }

        if ($any(SellerOrder::IN_TRANSIT_STATUSES) || $any(SellerOrder::DELIVERED_STATUSES)) {
            return $any(SellerOrder::PRE_SHIPMENT_STATUSES) ? SalesOrder::STATUS_PARTIALLY_SHIPPED : SalesOrder::STATUS_SHIPPED;
        }

        return count($active) < count($statuses) || $hasCancelledItems
            ? SalesOrder::STATUS_PARTIALLY_CANCELLED
            : SalesOrder::STATUS_CONFIRMED;
    }

    /**
     * FR-ORD-010: SELLERCODE/2026-27/000001, one series per seller per financial year, taken
     * once (M14 calls it on ready_to_ship; transition() does it too).
     */
    public function assignInvoiceNumber(SellerOrder $sellerOrder, bool $save = true): string
    {
        if ($sellerOrder->invoice_no !== null) {
            return $sellerOrder->invoice_no;
        }

        $sellerOrder->loadMissing('seller');
        $now = Carbon::now();

        $sellerOrder->invoice_no = $this->sequences->invoiceNumber((string) $sellerOrder->seller->code, $this->sequences->financialYear($now));
        $sellerOrder->invoice_date = $now->copy()->startOfDay();

        if ($save) {
            $sellerOrder->save();
        }

        return $sellerOrder->invoice_no;
    }
}
