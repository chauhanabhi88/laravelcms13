<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;
use Throwable;

/**
 * Safety net of the payment hand-off (plan M12 §5.3), run every 10 minutes. Orders still
 * waiting for payment some time after placement are reconciled with their latest payment:
 * captured or OTP-verified → confirmed (a missed event); failed, cancelled, missing, or open
 * long past its window → cancelled by the system and the stock released.
 */
class CancelAbandonedOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $limit = 200) {}

    /**
     * @return array{confirmed: int, cancelled: int}
     */
    public function handle(PaymentService $payments, OrderStatusService $statuses, OrderCancellationService $cancellations): array
    {
        $grace = max(1, (int) config('order.abandoned_grace_minutes', 30));
        $result = ['confirmed' => 0, 'cancelled' => 0];

        $orders = SalesOrder::query()
            ->whereIn('status', SalesOrder::AWAITING_PAYMENT_STATUSES)
            ->where('placed_at', '<', Carbon::now()->subMinutes($grace))
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        foreach ($orders as $order) {
            try {
                $payment = $payments->latestPayment($order->id);

                if ($payment !== null && ($payment->isCaptured() || ($payment->isCod() && $payment->status === Payment::STATUS_PENDING && ! $payment->awaitingCodOtp()))) {
                    $result['confirmed'] += $statuses->confirm($order) ? 1 : 0;

                    continue;
                }

                if ($payment !== null && in_array($payment->status, [...Payment::OPEN_PREPAID_STATUSES, Payment::STATUS_PENDING], true)
                    && ($payment->expires_at === null || $payment->expires_at->greaterThan(Carbon::now()->subMinutes($grace)))) {
                    // Still inside (or just past) its window: M11's sweep decides first.
                    continue;
                }

                $cancellations->cancelOrder($order, OrderCancellationService::BY_SYSTEM, trans('order::front.cancel.reasons_system.payment_window_expired'));
                $result['cancelled']++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $result;
    }
}
