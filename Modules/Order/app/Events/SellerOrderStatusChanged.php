<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SellerOrder;

/**
 * A seller order changed status (HasStateMachine transition event). Fired after commit.
 */
class SellerOrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SellerOrder $sellerOrder,
        public ?string $from,
        public string $to,
    ) {}
}
