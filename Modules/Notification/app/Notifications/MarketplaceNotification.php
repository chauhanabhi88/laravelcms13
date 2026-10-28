<?php

declare(strict_types=1);

namespace Modules\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Modules\Notification\Channels\InAppChannel;
use Modules\Notification\Channels\PushChannel;
use Modules\Notification\Channels\SmsChannel;
use Modules\Notification\Channels\TemplateMailChannel;
use Modules\Notification\Channels\WhatsAppChannel;
use Modules\Notification\Support\EventCatalogue;
use Modules\Notification\Support\EventDefinition;
use Modules\Notification\Support\NotificationSettings;

/**
 * The one notification class of the hub: a catalogue event, its data and the channels the
 * hub resolved. Queued on "notifications" after the surrounding transaction commits, so a
 * rolled-back order sends nothing. Each channel renders its own template.
 */
class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<string, class-string>
     */
    public const CHANNEL_CLASSES = [
        'mail' => TemplateMailChannel::class,
        'sms' => SmsChannel::class,
        'push' => PushChannel::class,
        'database' => InAppChannel::class,
        'whatsapp' => WhatsAppChannel::class,
    ];

    public int $tries = 3;

    /**
     * @param  array<string, scalar|null>  $data  template variables
     * @param  list<string>  $channels  channel names (keys of CHANNEL_CLASSES)
     * @param  array<string, int>  $logIds  notification_log row per channel
     */
    public function __construct(
        public readonly string $eventKey,
        public readonly array $data,
        public readonly array $channels,
        public readonly array $logIds = [],
    ) {
        $this->afterCommit();
        $this->onQueue((string) config('notification.queue', 'notifications'));
    }

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter(array_map(
            fn (string $channel): ?string => self::CHANNEL_CLASSES[$channel] ?? null,
            $this->channels,
        )));
    }

    /**
     * Promotional messages wait for the end of quiet hours.
     */
    public function withDelay(object $notifiable, string $channel): ?Carbon
    {
        return $this->definition()->isPromotional() ? NotificationSettings::quietHoursEndAfter(Carbon::now()) : null;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function definition(): EventDefinition
    {
        return app(EventCatalogue::class)->get($this->eventKey);
    }

    public function logIdFor(string $channel): ?int
    {
        return $this->logIds[$channel] ?? null;
    }

    /**
     * Channel name for a channel class (NotificationSent / NotificationFailed carry the class).
     */
    public static function channelName(string $channel): string
    {
        $name = array_search($channel, self::CHANNEL_CLASSES, true);

        return $name === false ? $channel : (string) $name;
    }
}
