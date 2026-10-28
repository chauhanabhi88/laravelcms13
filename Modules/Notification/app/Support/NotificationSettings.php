<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use Illuminate\Support\Carbon;

/**
 * Typed access to Settings > Notification with config fallbacks. 1/2 switches left blank
 * count as enabled.
 */
final class NotificationSettings
{
    public static function transactionalSmsEnabled(): bool
    {
        return self::flag('enable_sms_transactional');
    }

    public static function pushEnabled(): bool
    {
        return self::flag('enable_push');
    }

    /**
     * Quiet hours as [start, end] "HH:MM" strings, or null when switched off.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function quietHours(): ?array
    {
        $value = trim((string) settings('notification', 'quiet_hours'));
        $value = $value === '' ? (string) config('notification.quiet_hours', '') : $value;

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)$/', $value, $match)) {
            return null;
        }

        return [$match[1].':'.$match[2], $match[3].':'.$match[4]];
    }

    /**
     * When quiet hours that contain $now end, or null when $now is outside them.
     */
    public static function quietHoursEndAfter(Carbon $now): ?Carbon
    {
        $hours = self::quietHours();

        if ($hours === null || $hours[0] === $hours[1]) {
            return null;
        }

        $start = $now->copy()->setTimeFromTimeString($hours[0]);
        $end = $now->copy()->setTimeFromTimeString($hours[1]);

        if ($start->lessThan($end)) {
            return $now->betweenIncluded($start, $end->copy()->subSecond()) ? $end : null;
        }

        // Overnight window, e.g. 22:00-08:00.
        if ($now->greaterThanOrEqualTo($start)) {
            return $end->addDay();
        }

        return $now->lessThan($end) ? $end : null;
    }

    private static function flag(string $key): bool
    {
        $value = settings('notification', $key);

        return $value === null || $value === '' || (int) $value !== (int) config('core.no');
    }
}
