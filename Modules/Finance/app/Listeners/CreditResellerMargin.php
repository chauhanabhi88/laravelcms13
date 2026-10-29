<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\ResellerWalletService;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Delivered COD reseller order: the reseller's margin, pending until the return window ends
 * (plan M17 §5.8).
 */
class CreditResellerMargin
{
    public function __construct(private readonly ResellerWalletService $wallet) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_DELIVERED || $event->from === SellerOrder::STATUS_RETURN_REQUESTED) {
            return;
        }

        try {
            $this->wallet->creditMargins(SellerOrder::query()->findOrFail($event->sellerOrder->id));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
