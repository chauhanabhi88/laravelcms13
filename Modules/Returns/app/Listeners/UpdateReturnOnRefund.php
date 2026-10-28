<?php

declare(strict_types=1);

namespace Modules\Returns\Listeners;

use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Events\RefundProcessed;
use Modules\Returns\Services\ReturnRefundService;
use Throwable;

/**
 * The outcome of a return's refund (webhook, Finance marking a COD refund paid): refunded, or
 * failed and flagged for CX. Refunds of cancellations and RTOs carry no return id.
 */
class UpdateReturnOnRefund
{
    public function __construct(private readonly ReturnRefundService $refunds) {}

    public function processed(RefundProcessed $event): void
    {
        try {
            $this->refunds->markRefunded($event->refund);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function failed(RefundFailed $event): void
    {
        try {
            $this->refunds->markFailed($event->refund);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
