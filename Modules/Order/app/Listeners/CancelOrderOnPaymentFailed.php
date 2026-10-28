<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Models\Payment;

/**
 * The payment window expired, or the COD OTP was never verified (M11's 5-minute sweep): an
 * order still waiting for that payment is cancelled by the system and its stock released.
 * Nothing happens when the buyer has another open payment for the order (they switched).
 */
class CancelOrderOnPaymentFailed
{
    public function __construct(private readonly OrderCancellationService $cancellations) {}

    public function handle(PaymentFailed $event): void
    {
        $order = SalesOrder::query()->find($event->payment->sales_order_id);

        if ($order === null || ! $order->isAwaitingPayment()) {
            return;
        }

        $otherOpen = Payment::query()
            ->forOrder($order->id)
            ->whereKeyNot($event->payment->id)
            ->whereIn('status', [...Payment::OPEN_PREPAID_STATUSES, Payment::STATUS_PENDING, Payment::STATUS_CAPTURED])
            ->exists();

        if ($otherOpen) {
            return;
        }

        $order->forceFill(['payment_status' => $event->payment->status])->save();

        try {
            $this->cancellations->cancelOrder($order, OrderCancellationService::BY_SYSTEM, trans('order::front.cancel.reasons_system.'.$event->reason));
        } catch (OrderException $e) {
            report($e);
        }
    }
}
