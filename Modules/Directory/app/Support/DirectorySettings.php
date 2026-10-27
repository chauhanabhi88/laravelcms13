<?php

declare(strict_types=1);

namespace Modules\Directory\Support;

/**
 * Typed access to the Directory settings (admin > Settings), with config defaults,
 * so callers never read settings() without a fallback.
 */
class DirectorySettings
{
    /**
     * ISO-2 code of the marketplace country; states and cities reference countries by code.
     */
    public static function defaultCountry(): string
    {
        $country = settings('directory', 'default_country');

        return strtoupper(trim((string) ($country ?: config('directory.defaults.country', 'IN'))));
    }
}
