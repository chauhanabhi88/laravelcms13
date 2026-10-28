<?php

declare(strict_types=1);

namespace Modules\Payment\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Modules\Notification\Services\NotificationHub;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Services\Webhooks\RazorpayWebhookHandler;
use Throwable;

/**
 * Processes one stored webhook (queue config('payment.webhook_queue')). Already processed or
 * ignored events and unsigned rows are skipped, so a duplicate dispatch or a replay of a
 * finished event does nothing. Failures are stored on the row, retried with backoff, and
 * admins are alerted after the last attempt.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $webhookEventId)
    {
        $this->onQueue((string) config('payment.webhook_queue', 'webhooks'));
    }

    public function handle(RazorpayWebhookHandler $handler): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || ! $event->isReplayable() || in_array($event->status, [WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_IGNORED], true)) {
            return;
        }

        $event->forceFill(['attempts' => min(255, $event->attempts + 1)])->save();

        try {
            $status = $handler->handle($event);
        } catch (Throwable $e) {
            $event->forceFill(['status' => WebhookEvent::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();

            throw $e;
        }

        $event->forceFill(['status' => $status, 'error' => null, 'processed_at' => Carbon::now()])->save();
    }

    public function failed(Throwable $exception): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        try {
            app(NotificationHub::class)->eventForAdmins('admin.webhook.failed', [
                'provider' => $event?->provider ?? 'razorpay',
                'event_id' => $event?->event_id ?? (string) $this->webhookEventId,
                'error' => mb_substr($exception->getMessage(), 0, 500),
                'url' => route('admin.webhook_event.show', updateUrlParams([$this->webhookEventId])),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
