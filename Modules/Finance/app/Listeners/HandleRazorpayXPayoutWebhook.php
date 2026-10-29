<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\PayoutWebhookHandler;
use Modules\Seller\Events\RazorpayXWebhookReceived;

/**
 * payout.* events from the Seller module's signed RazorpayX webhook. A failure propagates so
 * the webhook answers 500 and RazorpayX delivers it again.
 */
class HandleRazorpayXPayoutWebhook
{
    public function __construct(private readonly PayoutWebhookHandler $handler) {}

    public function handle(RazorpayXWebhookReceived $event): void
    {
        if (str_starts_with($event->event, 'payout.')) {
            $this->handler->handle($event->event, $event->entity);
        }
    }
}
