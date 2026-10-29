<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\PayoutHoldService;
use Modules\Seller\Events\SellerEvent;
use Modules\Seller\Models\Seller;
use Throwable;

/**
 * Bank account changed, seller suspended / reactivated / approved (M03): payable entries move
 * between eligible and on_hold at once instead of waiting for the daily run (FR-FIN-009).
 */
class SyncPayoutHolds
{
    public function __construct(private readonly PayoutHoldService $holds) {}

    public function handle(SellerEvent $event): void
    {
        try {
            $seller = Seller::query()->find($event->seller->id);

            if ($seller !== null) {
                $this->holds->sync($seller);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
