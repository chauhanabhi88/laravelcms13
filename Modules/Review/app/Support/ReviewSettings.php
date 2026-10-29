<?php

declare(strict_types=1);

namespace Modules\Review\Support;

/**
 * Typed access to admin > Settings > Reviews with config('review.defaults') fallbacks, so
 * callers never read settings() without a default.
 */
final class ReviewSettings
{
    /**
     * Reviews that pass the automatic checks go live at once; otherwise every review waits
     * for a moderator.
     */
    public static function autoPublish(): bool
    {
        $value = self::get('auto_publish');

        return (int) (is_numeric($value) ? $value : config('review.defaults.auto_publish')) === (int) config('core.yes');
    }

    public static function minBodyLength(): int
    {
        return min(500, self::integer('min_body_length'));
    }

    public static function maxImages(): int
    {
        return max(0, min(10, self::integer('max_images')));
    }

    public static function editWindowDays(): int
    {
        return self::integer('edit_window_days');
    }

    /**
     * Abuse reports that send a published review back to the moderation queue.
     */
    public static function reportThreshold(): int
    {
        return max(1, self::integer('report_threshold'));
    }

    public static function promptDelayDays(): int
    {
        return min(60, self::integer('prompt_delay_days'));
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('review.defaults.'.$key)));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('review', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
