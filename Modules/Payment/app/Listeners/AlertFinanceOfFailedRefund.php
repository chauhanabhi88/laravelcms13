<?php

declare(strict_types=1);

namespace Modules\Payment\Listeners;

use Modules\Notification\Services\NotificationHub;
use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Models\Payment;
use Throwable;

/**
 * Tells admins who can see refunds (admin.refund.failed) that a refund needs attention.
 */
class AlertFinanceOfFailedRefund
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(RefundFailed $event): void
    {
        try {
            $refund = $event->refund;
            $orderNo = Payment::query()->whereKey($refund->payment_id)->value('order_no');

            $this->hub->eventForAdmins('admin.refund.failed', [
                'order_no' => (string) $orderNo,
                'amount' => ltrim($refund->amount->format(), '₹'),
                'error' => (string) $refund->failure_reason,
                'url' => route('admin.payment_refund.index', updateUrlParams()),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
