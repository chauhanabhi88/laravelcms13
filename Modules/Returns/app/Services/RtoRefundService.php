<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Modules\Core\Support\Money;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;
use Ramsey\Uuid\Uuid;

/**
 * Prepaid packages that came back undelivered (plan M15 §5.7): when the forward shipment is
 * rto_delivered, the buyer gets the package's items (after the coupon share) and its delivery
 * share back to source. COD packages collected nothing (Logistics closes their payment).
 * Idempotent per seller order.
 */
class RtoRefundService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly ReturnRefundService $returnRefunds,
    ) {}

    public function refund(Shipment $shipment): ?PaymentRefund
    {
        if ($shipment->direction !== Shipment::DIRECTION_FORWARD) {
            return null;
        }

        $sellerOrder = SellerOrder::query()->find($shipment->seller_order_id);
        $order = $sellerOrder !== null ? SalesOrder::query()->find($sellerOrder->sales_order_id) : null;

        if ($sellerOrder === null || $order === null || $order->isCod()) {
            return null;
        }

        $payment = $this->returnRefunds->refundablePayment($order);

        if ($payment === null || ! $payment->isPrepaid()) {
            return null;
        }

        $amount = SalesOrderItem::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->get()
            ->reduce(fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->netLineTotal()), $sellerOrder->delivery_charge_share);

        $available = $this->refunds->refundableAmount($payment);
        $amount = $amount->compare($available) > 0 ? $available : $amount;

        if (! $amount->isPositive()) {
            return null;
        }

        return $this->refunds->refund(
            $payment,
            $amount,
            PaymentRefund::MODE_SOURCE,
            null,
            trans('returns::returns.rto_refund_reason', ['order_no' => $sellerOrder->sub_order_no]),
            Uuid::uuid5(Uuid::NAMESPACE_URL, 'rto-refund:'.$sellerOrder->id)->toString(),
        );
    }
}
