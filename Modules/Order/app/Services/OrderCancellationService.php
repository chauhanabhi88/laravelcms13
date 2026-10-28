<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\Money;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\OrderSettings;
use Modules\Payment\Exceptions\RefundException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;
use Modules\Payment\Services\RefundService;
use Modules\Product\Services\InventoryService;
use Ramsey\Uuid\Uuid;

/**
 * Cancellation before shipment (plan M12 §5.5) by the customer, an admin, the system (payment
 * not completed) or the seller (M14 calls it with by = seller).
 *
 * In one transaction: the items become cancelled, their stock is released, the seller order
 * is cancelled when nothing of it is left (its totals are recomputed otherwise) and the sales
 * order's status is derived again. After commit, a captured prepaid payment is refunded for
 * the cancelled lines (plus the package's delivery share when the whole package goes), and an
 * order with nothing left has its unpaid payments closed.
 */
class OrderCancellationService
{
    public const BY_CUSTOMER = 'customer';

    public const BY_SELLER = 'seller';

    public const BY_ADMIN = 'admin';

    public const BY_SYSTEM = 'system';

    public const ACTORS = [self::BY_CUSTOMER, self::BY_SELLER, self::BY_ADMIN, self::BY_SYSTEM];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderStatusService $statuses,
        private readonly RefundService $refunds,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Whether $by may cancel this seller order now: before dispatch; a packed one only by
     * staff, or by the customer when the setting allows it.
     */
    public function canCancel(SellerOrder $sellerOrder, string $by = self::BY_CUSTOMER): bool
    {
        if (! $sellerOrder->isPreShipment()) {
            return false;
        }

        if ($sellerOrder->status === SellerOrder::STATUS_PACKED && $by === self::BY_CUSTOMER) {
            return OrderSettings::allowCustomerCancelAfterPacked();
        }

        return true;
    }

    /**
     * Cancel the given items of a seller order (null = all its active items).
     *
     * @param  list<int>|null  $itemIds
     * @return list<int> the cancelled item ids
     *
     * @throws OrderException
     */
    public function cancelItems(SellerOrder $sellerOrder, ?array $itemIds, string $by, string $reason, ?Model $actor = null): array
    {
        $by = in_array($by, self::ACTORS, true) ? $by : self::BY_SYSTEM;
        $reason = trim($reason) === '' ? trans('order::front.cancel.default_reason') : mb_substr(trim($reason), 0, 255);

        /** @var array{0: SalesOrder, 1: SellerOrder, 2: list<int>, 3: bool, 4: Money} $result */
        $result = DB::transaction(function () use ($sellerOrder, $itemIds, $by, $reason, $actor): array {
            $locked = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            $order = SalesOrder::query()->lockForUpdate()->findOrFail($locked->sales_order_id);

            if (! $this->canCancel($locked, $by)) {
                throw OrderException::notCancellable();
            }

            $items = SalesOrderItem::query()
                ->where('seller_order_id', $locked->id)
                ->where('status', SalesOrderItem::STATUS_ACTIVE)
                ->when($itemIds !== null, fn ($query) => $query->whereIn('id', $itemIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw new OrderException(trans('order::front.errors.nothing_to_cancel'), 'nothing_to_cancel');
            }

            $this->inventory->release(
                $items->map(fn (SalesOrderItem $item): array => ['variant_id' => $item->variant_id, 'qty' => $item->quantity])->values()->all(),
                $order,
                $actor,
                'Cancelled by '.$by,
            );

            foreach ($items as $item) {
                $item->forceFill(['status' => SalesOrderItem::STATUS_CANCELLED, 'cancelled_qty' => $item->quantity])->save();
            }

            $whole = ! SalesOrderItem::query()->where('seller_order_id', $locked->id)->where('status', SalesOrderItem::STATUS_ACTIVE)->exists();

            if ($whole) {
                $locked->forceFill(['cancelled_by' => $by, 'cancel_reason' => $reason]);
                $this->statuses->apply($locked, SellerOrder::STATUS_CANCELLED, $reason, $actor, $actor === null ? $by : null);
            } else {
                $this->recomputeTotals($locked);
            }

            $this->statuses->refresh($order);

            $refund = $this->refundableFor($order, $items->all(), $whole ? $locked->delivery_charge_share : Money::zero());
            $ids = $items->modelKeys();

            OrderCancelled::dispatch($order, $locked, $ids, $by, $reason, $whole, $refund);

            return [$order, $locked, $ids, $whole, $refund];
        }, 3);

        [$order, $locked, $ids, , $refund] = $result;

        if ($refund->isPositive()) {
            $this->refund($order, $locked, $ids, $refund, $reason);
        }

        if ($order->status === SalesOrder::STATUS_CANCELLED) {
            $this->payments->cancelForOrder($order->id, 'order_cancelled');
        }

        return $ids;
    }

    /**
     * Cancel every seller order of the order that $by may still cancel.
     *
     * @return int seller orders cancelled
     *
     * @throws OrderException when none can be cancelled
     */
    public function cancelOrder(SalesOrder $order, string $by, string $reason, ?Model $actor = null): int
    {
        $sellerOrders = SellerOrder::query()->where('sales_order_id', $order->id)->orderBy('id')->get()
            ->filter(fn (SellerOrder $sellerOrder): bool => $this->canCancel($sellerOrder, $by));

        if ($sellerOrders->isEmpty()) {
            throw OrderException::notCancellable();
        }

        foreach ($sellerOrders as $sellerOrder) {
            $this->cancelItems($sellerOrder, null, $by, $reason, $actor);
        }

        $order->refresh();

        return $sellerOrders->count();
    }

    /**
     * A customer's cancellation from the order page or the API: the given items (grouped by
     * their seller order), or everything that can still be cancelled.
     *
     * @param  list<int>|null  $itemIds
     * @return int items cancelled
     *
     * @throws OrderException
     */
    public function cancelForCustomer(SalesOrder $order, ?array $itemIds, string $reason, ?Model $actor = null): int
    {
        if ($itemIds === null) {
            $before = SalesOrderItem::query()->where('sales_order_id', $order->id)->where('status', SalesOrderItem::STATUS_ACTIVE)->count();
            $this->cancelOrder($order, self::BY_CUSTOMER, $reason, $actor);

            return $before - SalesOrderItem::query()->where('sales_order_id', $order->id)->where('status', SalesOrderItem::STATUS_ACTIVE)->count();
        }

        $items = SalesOrderItem::query()
            ->where('sales_order_id', $order->id)
            ->whereIn('id', $itemIds)
            ->where('status', SalesOrderItem::STATUS_ACTIVE)
            ->get();

        if ($items->isEmpty()) {
            throw new OrderException(trans('order::front.errors.nothing_to_cancel'), 'nothing_to_cancel');
        }

        $sellerOrders = SellerOrder::query()->whereIn('id', $items->pluck('seller_order_id')->unique()->all())->orderBy('id')->get();

        foreach ($sellerOrders as $sellerOrder) {
            if (! $this->canCancel($sellerOrder, self::BY_CUSTOMER)) {
                throw OrderException::notCancellable();
            }
        }

        $count = 0;

        foreach ($sellerOrders as $sellerOrder) {
            $ids = $items->where('seller_order_id', $sellerOrder->id)->modelKeys();
            $count += count($this->cancelItems($sellerOrder, array_values($ids), self::BY_CUSTOMER, $reason, $actor));
        }

        $order->refresh();

        return $count;
    }

    /**
     * Items the customer may still cancel (their seller order is cancellable by the customer).
     *
     * @return list<int>
     */
    public function cancellableItemIds(SalesOrder $order): array
    {
        $order->loadMissing('sellerOrders.items');
        $ids = [];

        foreach ($order->sellerOrders as $sellerOrder) {
            if (! $this->canCancel($sellerOrder, self::BY_CUSTOMER)) {
                continue;
            }

            foreach ($sellerOrder->items as $item) {
                if ($item->isActive()) {
                    $ids[] = $item->id;
                }
            }
        }

        return $ids;
    }

    /**
     * The order's captured prepaid payment, if any.
     */
    public function capturedPayment(SalesOrder $order): ?Payment
    {
        return Payment::query()
            ->forOrder($order->id)
            ->where('method', '!=', Payment::METHOD_COD)
            ->whereIn('status', [Payment::STATUS_CAPTURED, Payment::STATUS_PARTIALLY_REFUNDED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Refund a whole captured payment of an order that is already cancelled (a capture that
     * arrived after the payment window closed).
     */
    public function refundLateCapture(Payment $payment, string $reason): ?PaymentRefund
    {
        $amount = $this->refunds->refundableAmount($payment);

        if (! $amount->isPositive()) {
            return null;
        }

        try {
            return $this->refunds->refund($payment, $amount, PaymentRefund::MODE_SOURCE, null, $reason, Uuid::uuid5(Uuid::NAMESPACE_URL, 'order-late-capture:'.$payment->id)->toString());
        } catch (RefundException $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  list<SalesOrderItem>  $items
     */
    protected function refundableFor(SalesOrder $order, array $items, Money $deliveryShare): Money
    {
        if ($order->isCod() || $this->capturedPayment($order) === null) {
            return Money::zero();
        }

        return array_reduce($items, fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->netLineTotal()), $deliveryShare);
    }

    /**
     * @param  list<int>  $itemIds
     */
    protected function refund(SalesOrder $order, SellerOrder $sellerOrder, array $itemIds, Money $amount, string $reason): void
    {
        $payment = $this->capturedPayment($order);

        if ($payment === null) {
            return;
        }

        $refundable = $this->refunds->refundableAmount($payment);
        $amount = $amount->compare($refundable) > 0 ? $refundable : $amount;

        if (! $amount->isPositive()) {
            return;
        }

        sort($itemIds);
        $key = Uuid::uuid5(Uuid::NAMESPACE_URL, 'order-cancel:'.$sellerOrder->id.':'.implode(',', $itemIds))->toString();

        try {
            $this->refunds->refund($payment, $amount, PaymentRefund::MODE_SOURCE, null, $reason, $key, null, count($itemIds) === 1 ? $itemIds[0] : null);
        } catch (RefundException $e) {
            // The cancellation stands; Finance retries the refund from the refunds screen.
            report($e);
            Log::warning('order cancellation refund failed', ['sales_order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Totals of a partly cancelled seller order over its remaining active items.
     */
    protected function recomputeTotals(SellerOrder $sellerOrder): void
    {
        $active = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->where('status', SalesOrderItem::STATUS_ACTIVE)->get();

        $sellerOrder->forceFill([
            'items_subtotal' => $active->reduce(fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->lineTotal()), Money::zero()),
            'tax_total' => $active->reduce(fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->taxAmount()), Money::zero()),
            'commission_total' => $active->reduce(fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->commission_amount), Money::zero()),
        ])->save();
    }
}
