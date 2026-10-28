<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Notification\Channels\ChannelResult;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Notifications\MarketplaceNotification;
use Throwable;

/**
 * Writes the outcome of each hub channel to notification_log: the "queued" row the hub
 * created becomes sent / skipped / failed. Other notifications are ignored.
 */
class LogNotificationSent
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $notification = $event->notification;

        if (! $notification instanceof MarketplaceNotification) {
            return;
        }

        $channel = MarketplaceNotification::channelName((string) $event->channel);

        if ($event instanceof NotificationFailed) {
            $exception = $event->data['exception'] ?? null;
            $this->write($notification, $channel, $event->notifiable, [
                'status' => NotificationLog::STATUS_FAILED,
                'error' => Str::limit($exception instanceof Throwable ? $exception->getMessage() : 'Delivery failed.', 2000),
            ]);

            return;
        }

        $result = $event->response;

        if (! $result instanceof ChannelResult) {
            return;
        }

        $this->write($notification, $channel, $event->notifiable, array_filter([
            'status' => $result->status,
            'destination' => $result->destination !== '' ? $result->destination : null,
            'provider_message_id' => $result->providerMessageId !== null ? Str::limit($result->providerMessageId, 188) : null,
            'sent_at' => $result->wasSent() ? Carbon::now() : null,
        ], fn ($value): bool => $value !== null) + ['error' => $result->error]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function write(MarketplaceNotification $notification, string $channel, mixed $notifiable, array $values): void
    {
        try {
            $values['notification_id'] = (string) $notification->id;
            $logId = $notification->logIdFor($channel);
            $log = $logId !== null ? NotificationLog::query()->find($logId) : null;

            if ($log instanceof NotificationLog) {
                $log->forceFill($values)->save();

                return;
            }

            NotificationLog::query()->create($values + [
                'event_key' => $notification->eventKey,
                'channel' => $channel,
                'notifiable_type' => $notifiable instanceof Model ? $notifiable->getMorphClass() : null,
                'notifiable_id' => $notifiable instanceof Model ? $notifiable->getKey() : null,
                'payload' => ['data' => $notification->data],
            ]);
        } catch (Throwable $e) {
            // The log must never turn a delivered message into a failed job.
            report($e);
        }
    }
}
