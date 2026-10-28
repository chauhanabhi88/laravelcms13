<?php

declare(strict_types=1);

namespace Modules\Payment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\Payment;

/**
 * A prepaid payment was captured (from the checkout callback, a webhook or the expiry
 * sweep). Fired once per payment, after commit. M12 moves the order to pending_acceptance.
 */
class PaymentCaptured implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
