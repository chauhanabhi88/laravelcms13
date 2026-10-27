<?php

declare(strict_types=1);

namespace Modules\Seller\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerEvent;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerRejected;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Notifications\SellerDecisionNotification;
use Throwable;

/**
 * Emails the seller's owner login about an admin decision. A stand-in until the
 * notification hub (M09) takes these events over with templates and SMS.
 */
class NotifySellerOfDecision
{
    /**
     * @var array<class-string<SellerEvent>, string>
     */
    private const DECISIONS = [
        SellerApproved::class => 'approved',
        SellerRejected::class => 'rejected',
        SellerChangesRequested::class => 'changes_requested',
        SellerSuspended::class => 'suspended',
        SellerReactivated::class => 'reactivated',
    ];

    public function handle(SellerEvent $event): void
    {
        $decision = self::DECISIONS[$event::class] ?? null;
        $seller = $event->seller->loadMissing('owner');

        if ($decision === null) {
            return;
        }

        $notification = new SellerDecisionNotification($decision, [
            'store' => $seller->displayName(),
            'reason' => (string) $event->reason,
            'app' => (string) config('app.name'),
            'url' => frontRoute('seller.login'),
        ]);

        try {
            if ($seller->owner !== null) {
                $seller->owner->notify($notification);
            } elseif ($seller->email) {
                Notification::route('mail', $seller->email)->notify($notification);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
