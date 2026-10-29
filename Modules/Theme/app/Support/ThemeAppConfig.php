<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Modules\Core\Contracts\ProvidesAppConfig;

/**
 * The "support" and "stores" sections of GET /api/v1/app/config (contacts and app-store links).
 */
final class ThemeAppConfig implements ProvidesAppConfig
{
    public function appConfigKey(): string
    {
        return 'support';
    }

    public function appConfig(): array
    {
        return [
            'phone' => ThemeSettings::supportPhone(),
            'email' => ThemeSettings::supportEmail(),
            'play_store_url' => ThemeSettings::playStoreUrl(),
            'app_store_url' => ThemeSettings::appStoreUrl(),
        ];
    }
}
