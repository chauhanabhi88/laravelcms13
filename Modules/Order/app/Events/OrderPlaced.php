<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SalesOrder;

/**
 * A sales order was written (stock reserved), before its payment is known. Fired after
 * commit. ClearCartAfterOrder removes the purchased lines from the cart.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<int>  $cartItemIds  the cart lines that became order items
     */
    public function __construct(
        public SalesOrder $order,
        public string $cartType,
        public ?int $cartId,
        public array $cartItemIds,
    ) {}
}
