<?php

declare(strict_types=1);

namespace Modules\Payment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\Payment;

/**
 * The buyer gave up on prepaid and chose Cash on Delivery for the same order. M12 updates
 * the order's payment method (and COD fee); the prepaid payment is now cancelled.
 */
class PaymentSwitchedToCod implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $codPayment, public Payment $cancelledPayment) {}
}
