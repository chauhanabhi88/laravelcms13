<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\Events\PaymentSwitchedToCod;
use Modules\Payment\Models\Payment;

/**
 * The buyer gave up on prepaid and chose Cash on Delivery on the pay page (M11): the order
 * becomes a COD order (fee, reseller margin, total) and is confirmed, or waits for the OTP.
 */
class SwitchOrderToCod
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function handle(PaymentSwitchedToCod $event): void
    {
        $payment = $event->codPayment;
        $order = SalesOrder::query()->with('items')->find($payment->sales_order_id);

        if ($order === null || ! $order->isAwaitingPayment()) {
            return;
        }

        DB::transaction(function () use ($order, $payment): void {
            $margin = Money::zero();

            if ($order->isResellerOrder()) {
                foreach ($order->items as $item) {
                    /** @var SalesOrderItem $item */
                    $item->forceFill(['reseller_margin' => $item->potentialMargin()])->save();
                    $margin = $margin->plus($item->potentialMargin());
                }
            }

            $order->forceFill([
                'payment_method' => Payment::METHOD_COD,
                'payment_status' => $payment->status,
                'cod_fee' => CartSettings::codFee(),
                'reseller_margin_total' => $margin,
                'grand_total' => $payment->amount,
            ])->save();
        });

        if ($payment->awaitingCodOtp()) {
            $this->statuses->markAwaitingCodOtp($order);
        } else {
            $this->statuses->confirm($order);
        }
    }
}
