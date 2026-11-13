<?php

/*
| Storefront branding and links (admin > Settings > Theme). Read through
| Modules\Theme\Support\ThemeSettings, never with settings() directly.
*/

$textSetting = fn (string $key, string $placeholder = ''): array => [
    'label' => "theme::theme.settings.{$key}.label",
    'type' => 'text',
    'placeholder' => $placeholder,
    'storage' => 'db',
    'comment' => "theme::theme.settings.{$key}.comment",
];

return [
    'theme::theme.settings.storefront' => [
        'logo_url' => $textSetting('logo_url', '/modules/theme/frontend/images/logo.png'),
        'logo_dark_url' => $textSetting('logo_dark_url'),
        'favicon_url' => $textSetting('favicon_url', '/favicon.ico'),
        'show_download_app' => [
            'label' => 'theme::theme.settings.show_download_app.label',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db',
            'default' => config('core.no'),
            'comment' => 'theme::theme.settings.show_download_app.comment',
        ],
        'app_store_url' => $textSetting('app_store_url', 'https://'),
        'play_store_url' => $textSetting('play_store_url', 'https://'),
        'support_phone' => $textSetting('support_phone'),
        'support_email' => $textSetting('support_email'),
        'facebook_url' => $textSetting('facebook_url', 'https://'),
        'instagram_url' => $textSetting('instagram_url', 'https://'),
        'youtube_url' => $textSetting('youtube_url', 'https://'),
        'x_url' => $textSetting('x_url', 'https://'),
        // Plan M21: default share image (E5), analytics (E7) and the grievance officer (NFR-CMP-01).
        'og_image_url' => $textSetting('og_image_url', 'https://'),
        'gtm_container_id' => $textSetting('gtm_container_id', 'GTM-XXXXXXX'),
        'ga4_measurement_id' => $textSetting('ga4_measurement_id', 'G-XXXXXXXXXX'),
        'grievance_officer_name' => $textSetting('grievance_officer_name'),
        'grievance_officer_designation' => $textSetting('grievance_officer_designation'),
        'grievance_officer_email' => $textSetting('grievance_officer_email'),
        'grievance_officer_phone' => $textSetting('grievance_officer_phone'),
        'grievance_officer_address' => $textSetting('grievance_officer_address'),
    ],
];
