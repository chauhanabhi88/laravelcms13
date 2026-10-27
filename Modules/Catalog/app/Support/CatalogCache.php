<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Versioned cache for derived taxonomy data (tree, inherited defaults, resolved attributes).
 *
 * Every key embeds the current version; flush() swaps the version, so all entries are
 * dropped at once on any cache store, without tags or key bookkeeping. Stale entries
 * simply age out with their TTL.
 */
class CatalogCache
{
    protected const VERSION_KEY = 'catalog.cache.version';

    public function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), (int) config('catalog.cache_ttl', 86400), $callback);
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    public function key(string $key): string
    {
        return 'catalog.'.$this->version().'.'.$key;
    }

    protected function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn (): string => Str::random(12));
    }
}
