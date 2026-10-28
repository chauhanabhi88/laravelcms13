<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Payment\Models\PaymentRefund;

/**
 * Pays a COD refund to the buyer's bank account or UPI id (RazorpayX payout, M17).
 * Until M17 binds one, the null implementation leaves the refund pending for Finance to
 * mark as paid by hand.
 */
interface CodRefundPayout
{
    /**
     * Start the payout; returns the payout id, or null when no payout rail is configured.
     */
    public function payout(PaymentRefund $refund): ?string;
}
