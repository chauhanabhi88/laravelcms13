<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Modules\Order\Models\SellerOrder;

/**
 * Posts the seller ledger entries of a confirmed seller order (sale, commission, fees). Bound
 * to NullLedgerPoster (bindIf) until the Finance module (M17) binds its own.
 */
interface LedgerPoster
{
    public function postSale(SellerOrder $sellerOrder): void;
}
