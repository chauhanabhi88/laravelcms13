<?php

declare(strict_types=1);

namespace Modules\Promotion\Listeners;

use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Modules\Promotion\Services\ReferralService;
use Throwable;

/**
 * A referred customer's first delivered package qualifies their referral (a return that is
 * withdrawn, return_requested → delivered, is not a new delivery).
 */
class QualifyReferralOnDelivered
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_DELIVERED || $event->from === SellerOrder::STATUS_RETURN_REQUESTED) {
            return;
        }

        try {
            $this->referrals->qualify($event->sellerOrder);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
