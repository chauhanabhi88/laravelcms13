<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

/**
 * Typed access to the storefront settings (admin > Settings > Theme), with config
 * defaults, so callers never read settings() without a fallback.
 */
class ThemeSettings
{
    /**
     * The configured logo, else the theme's default file when it was published; null means
     * "no logo image" (<x-theme::brand-logo> then shows the site name as a wordmark).
     */
    public static function logoUrl(): ?string
    {
        $default = (string) config('theme.frontend_logo');

        return self::url(self::get('logo_url'))
            ?? ($default !== '' && is_file(public_path($default)) ? asset($default) : null);
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

    /**
     * Share image for pages without their own (plan M21 E5).
     */
    public static function defaultOgImageUrl(): ?string
    {
        return self::url(self::get('og_image_url'));
    }

    /**
     * Google Tag Manager container, only when it looks like one (plan M21 E7).
     */
    public static function gtmContainerId(): ?string
    {
        $id = strtoupper((string) self::text(self::get('gtm_container_id')));

        return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) ? $id : null;
    }

    /**
     * GA4 measurement ID, used only without a Tag Manager container.
     */
    public static function ga4MeasurementId(): ?string
    {
        $id = strtoupper((string) self::text(self::get('ga4_measurement_id')));

        return self::gtmContainerId() === null && preg_match('/^G-[A-Z0-9]{4,15}$/', $id) ? $id : null;
    }

    public static function analyticsEnabled(): bool
    {
        return self::gtmContainerId() !== null || self::ga4MeasurementId() !== null;
    }

    /**
     * Grievance officer contact (Consumer Protection (E-Commerce) Rules 2020), null until a
     * name and at least one way to reach them are set.
     *
     * @return array{name: string, designation: ?string, email: ?string, phone: ?string, address: ?string}|null
     */
    public static function grievanceOfficer(): ?array
    {
        $officer = [
            'name' => self::text(self::get('grievance_officer_name')),
            'designation' => self::text(self::get('grievance_officer_designation')),
            'email' => self::text(self::get('grievance_officer_email')),
            'phone' => self::text(self::get('grievance_officer_phone')),
            'address' => self::text(self::get('grievance_officer_address')),
        ];

        if ($officer['name'] === null || ($officer['email'] === null && $officer['phone'] === null && $officer['address'] === null)) {
            return null;
        }

        return $officer;
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
