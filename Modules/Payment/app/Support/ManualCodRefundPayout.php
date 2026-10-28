<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Payment\Contracts\CodRefundPayout;
use Modules\Payment\Models\PaymentRefund;

/**
 * No payout rail yet (RazorpayX payouts arrive with M17): the COD refund stays pending and
 * Finance marks it paid with the bank UTR from the admin refunds screen.
 */
class ManualCodRefundPayout implements CodRefundPayout
{
    public function payout(PaymentRefund $refund): ?string
    {
        return null;
    }
}
