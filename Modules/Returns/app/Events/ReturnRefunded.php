<?php

declare(strict_types=1);

namespace Modules\Returns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Models\ReturnRequest;

/**
 * The refund of a return reached the customer (after commit). M17 posts the return reversal
 * and the credit note from it.
 */
class ReturnRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
        public PaymentRefund $refund,
    ) {}
}
