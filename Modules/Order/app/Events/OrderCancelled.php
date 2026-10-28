<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;

/**
 * Items of a seller order were cancelled (stock released). $wholeSellerOrder when nothing of
 * it is left; $refund is what goes back to a prepaid buyer (zero for COD). After commit.
 */
class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<int>  $itemIds
     */
    public function __construct(
        public SalesOrder $order,
        public SellerOrder $sellerOrder,
        public array $itemIds,
        public string $cancelledBy,
        public string $reason,
        public bool $wholeSellerOrder,
        public Money $refund,
    ) {}
}
