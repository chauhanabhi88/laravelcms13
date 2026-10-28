<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationTemplate;

/**
 * Labelled select options for the admin screens.
 */
final class NotificationOptions
{
    /**
     * @return array<string, string> event key => "order.shipped — Order shipped"
     */
    public static function events(?string $audience = null): array
    {
        $options = [];

        foreach (app(EventCatalogue::class)->keys($audience) as $key) {
            $options[$key] = $key.' — '.self::eventLabel($key);
        }

        return $options;
    }

    public static function eventLabel(string $key): string
    {
        $translationKey = 'notification::events.'.$key;
        $label = trans($translationKey);

        return $label === $translationKey ? $key : $label;
    }

    /**
     * @return array<string, string>
     */
    public static function channels(bool $withMail = true): array
    {
        $channels = $withMail ? (array) config('notification.channels', []) : NotificationTemplate::CHANNELS;

        return self::labelled($channels, 'notification::notification.channels.');
    }

    /**
     * @return array<string, string>
     */
    public static function audiences(): array
    {
        return self::labelled((array) config('notification.audiences', []), 'notification::notification.audiences.');
    }

    /**
     * @return array<string, string>
     */
    public static function logStatuses(): array
    {
        return self::labelled(NotificationLog::STATUSES, 'notification::notification_log.statuses.');
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private static function labelled(array $values, string $prefix): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[(string) $value] = trans($prefix.$value);
        }

        return $options;
    }
}
