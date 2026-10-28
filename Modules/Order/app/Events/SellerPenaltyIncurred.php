<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SellerOrder;

/**
 * A seller cancelled an accepted order, or missed its dispatch deadline (plan M14 §5.3/§5.6).
 * The penalty hook: Finance (M17) posts the ledger entry from
 * settings('finance', 'seller_cancel_penalty'); nothing listens before M17.
 */
class SellerPenaltyIncurred implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public const KIND_SELLER_CANCEL = 'seller_cancel';

    public const KIND_SLA_BREACH = 'sla_breach';

    public function __construct(
        public SellerOrder $sellerOrder,
        public string $kind,
        public string $reason,
    ) {}
}
