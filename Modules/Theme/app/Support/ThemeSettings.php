<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

/**
 * Typed access to the storefront settings (admin > Settings > Theme), with config
 * defaults, so callers never read settings() without a fallback.
 */
class ThemeSettings
{
    public static function logoUrl(): string
    {
        return self::url(self::get('logo_url')) ?? asset((string) config('theme.frontend_logo'));
    }

    public static function logoDarkUrl(): ?string
    {
        return self::url(self::get('logo_dark_url'));
    }

    public static function faviconUrl(): string
    {
        return self::url(self::get('favicon_url')) ?? asset('favicon.ico');
    }

    public static function appStoreUrl(): ?string
    {
        return self::url(self::get('app_store_url'));
    }

    public static function playStoreUrl(): ?string
    {
        return self::url(self::get('play_store_url'));
    }

    /**
     * "Download App" shows only when switched on and at least one store link exists.
     */
    public static function showDownloadApp(): bool
    {
        return (int) self::get('show_download_app', config('theme.defaults.show_download_app')) === (int) config('core.yes')
            && (self::appStoreUrl() || self::playStoreUrl());
    }

    /**
     * The store link the header button uses (Android first).
     */
    public static function downloadAppUrl(): ?string
    {
        return self::showDownloadApp() ? (self::playStoreUrl() ?? self::appStoreUrl()) : null;
    }

    public static function supportPhone(): ?string
    {
        return self::text(self::get('support_phone'));
    }

    public static function supportEmail(): ?string
    {
        return self::text(self::get('support_email'));
    }

    /**
     * @return array<string, string> network key => URL, only for configured networks
     */
    public static function socialLinks(): array
    {
        $links = [];

        foreach (['facebook', 'instagram', 'youtube', 'x'] as $network) {
            if ($url = self::url(self::get("{$network}_url"))) {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    protected static function get(string $key, mixed $default = null): mixed
    {
        $settings = once(fn (): array => (array) settings('theme'));

        return filled($settings[$key] ?? null) ? $settings[$key] : $default;
    }

    protected static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Absolute http(s) URLs pass through; site paths become asset URLs; anything else is ignored.
     */
    protected static function url(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return str_starts_with($value, '/') ? asset(ltrim($value, '/')) : null;
    }
}
