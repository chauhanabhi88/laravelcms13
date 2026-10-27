<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Theme\Contracts\MegaMenuProvider;

/**
 * Cached access to the category tree, per locale.
 *
 * The Catalog module calls flush() when its tree changes (CatalogTreeChanged, M02).
 */
class MegaMenu
{
    protected const LOCALES_KEY = 'theme.megamenu.locales';

    public function __construct(protected MegaMenuProvider $provider) {}

    /**
     * @return array<int, array{id: int, name: string, url: string, image: ?string, children: array<int, mixed>}>
     */
    public function tree(): array
    {
        if ($this->provider instanceof NullMegaMenuProvider) {
            return [];
        }

        $locale = app()->getLocale();
        $this->rememberLocale($locale);

        return Cache::remember(
            $this->cacheKey($locale),
            (int) config('theme.mega_menu.cache_ttl', 3600),
            fn (): array => $this->provider->tree((int) config('theme.mega_menu.depth', 3)),
        );
    }

    /**
     * Forget the cached tree for every locale that has been cached.
     */
    public function flush(): void
    {
        foreach ((array) Cache::pull(self::LOCALES_KEY, []) as $locale) {
            Cache::forget($this->cacheKey((string) $locale));
        }
    }

    protected function rememberLocale(string $locale): void
    {
        $locales = (array) Cache::get(self::LOCALES_KEY, []);

        if (! in_array($locale, $locales, true)) {
            Cache::forever(self::LOCALES_KEY, [...$locales, $locale]);
        }
    }

    protected function cacheKey(string $locale): string
    {
        return 'theme.megamenu.'.$locale;
    }
}
