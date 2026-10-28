<?php

declare(strict_types=1);

namespace Modules\Payment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\PaymentRefund;

/**
 * A refund reached the buyer. Notifies the customer (M09); M17 posts the ledger entry.
 */
class RefundProcessed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentRefund $refund) {}
}
