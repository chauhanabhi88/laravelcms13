<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderPaid;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Events\PaymentCaptured;

/**
 * Prepaid captured (M11): the seller orders go to pending_acceptance and the order is
 * confirmed. A capture that reaches an order already cancelled (window expired, cancelled
 * by the buyer) is refunded in full.
 */
class ConfirmOrderOnPaymentCaptured
{
    public function __construct(
        private readonly OrderStatusService $statuses,
        private readonly OrderCancellationService $cancellations,
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $payment = $event->payment;
        $order = SalesOrder::query()->find($payment->sales_order_id);

        if ($order === null) {
            return;
        }

        $order->forceFill(['payment_method' => $payment->method, 'payment_status' => $payment->status])->save();

        if ($order->status === SalesOrder::STATUS_CANCELLED) {
            $this->cancellations->refundLateCapture($payment, trans('order::front.cancel.late_capture'));

            return;
        }

        if ($this->statuses->confirm($order)) {
            OrderPaid::dispatch($order->refresh(), $payment);
        }
    }
}
