<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Events\CodPaymentConfirmed;

/**
 * The buyer verified the COD OTP (M11): the held seller orders are released for fulfilment.
 */
class ConfirmOrderOnCodVerified
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function handle(CodPaymentConfirmed $event): void
    {
        $order = SalesOrder::query()->find($event->payment->sales_order_id);

        if ($order === null || $order->status === SalesOrder::STATUS_CANCELLED) {
            return;
        }

        $this->statuses->confirm($order);
    }
}
