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
    ],
];
