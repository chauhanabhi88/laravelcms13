<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Models\SellerOrder;

/**
 * No seller ledger before M17: nothing to post.
 */
class NullLedgerPoster implements LedgerPoster
{
    public function postSale(SellerOrder $sellerOrder): void {}
}
