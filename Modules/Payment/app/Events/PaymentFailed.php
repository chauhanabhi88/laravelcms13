<?php

declare(strict_types=1);

namespace Modules\Payment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\Payment;

/**
 * A payment ended without money: the prepaid window expired (payment_window_expired) or a
 * COD OTP was never verified (cod_otp_expired). M12 cancels the order and releases stock.
 */
class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public const REASON_WINDOW_EXPIRED = 'payment_window_expired';

    public const REASON_COD_OTP_EXPIRED = 'cod_otp_expired';

    public function __construct(public Payment $payment, public string $reason) {}
}
