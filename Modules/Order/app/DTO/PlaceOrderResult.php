<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

use Modules\Order\Models\SalesOrder;
use Modules\Payment\Models\Payment;

/**
 * A placed (or, on a repeated submit, the already placed) order and what the buyer does next:
 * pay online, confirm the COD OTP, or nothing (the success page).
 */
final readonly class PlaceOrderResult
{
    public const NEXT_PAY = 'pay';

    public const NEXT_COD_OTP = 'cod_otp';

    public const NEXT_SUCCESS = 'success';

    public function __construct(
        public SalesOrder $order,
        public ?Payment $payment,
        public bool $alreadyPlaced = false,
    ) {}

    public function nextAction(): string
    {
        return match (true) {
            $this->payment === null => $this->order->isCod() ? self::NEXT_SUCCESS : self::NEXT_PAY,
            $this->payment->awaitingCodOtp() => self::NEXT_COD_OTP,
            $this->payment->isAwaitingPayment() => self::NEXT_PAY,
            default => self::NEXT_SUCCESS,
        };
    }

    public function nextUrl(): string
    {
        $params = ['orderNo' => $this->order->order_no];

        return match ($this->nextAction()) {
            self::NEXT_PAY => frontRoute('payment.pay', $params),
            self::NEXT_COD_OTP => frontRoute('payment.cod_otp.show', $params),
            default => $this->order->confirmationUrl(),
        };
    }
}
