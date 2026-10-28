<?php

declare(strict_types=1);

namespace Modules\Payment\Listeners;

use Modules\Customer\Models\Customer;
use Modules\Notification\Services\NotificationHub;
use Modules\Payment\Events\RefundProcessed;
use Modules\Payment\Models\Payment;
use Throwable;

/**
 * "Refund processed" to the customer (catalogue event refund.processed). A notification
 * failure never undoes the refund.
 */
class SendRefundProcessedNotification
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(RefundProcessed $event): void
    {
        try {
            $refund = $event->refund;
            $payment = Payment::query()->find($refund->payment_id);
            $customer = $payment === null ? null : Customer::query()->find($payment->customer_id);

            if ($customer === null) {
                return;
            }

            $this->hub->event('refund.processed', $customer, [
                'order_no' => (string) $payment->order_no,
                'amount' => ltrim($refund->amount->format(), '₹'),
                'refund_mode' => trans('payment::front.refund_modes.'.$refund->mode),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
