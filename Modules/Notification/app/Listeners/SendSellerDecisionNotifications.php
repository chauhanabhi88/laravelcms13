<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\NotificationHub;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerEvent;
use Modules\Seller\Events\SellerRejected;
use Throwable;

/**
 * KYC decisions as SMS + in-app messages ("seller.kyc.*"). The decision email stays with the
 * Seller module's SellerDecisionNotification.
 */
class SendSellerDecisionNotifications
{
    /**
     * @var array<class-string<SellerEvent>, string>
     */
    private const EVENTS = [
        SellerApproved::class => 'seller.kyc.approved',
        SellerRejected::class => 'seller.kyc.rejected',
        SellerChangesRequested::class => 'seller.kyc.changes_requested',
    ];

    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(SellerEvent $event): void
    {
        $key = self::EVENTS[$event::class] ?? null;

        if ($key === null) {
            return;
        }

        try {
            $this->hub->eventForSeller($key, $event->seller, [
                'store' => $event->seller->displayName(),
                'reason' => (string) $event->reason,
                'url' => Route::has('seller.dashboard') ? frontRoute('seller.dashboard') : '',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
