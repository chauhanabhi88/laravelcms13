<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Versioned cache for storefront fragments. Each bucket ("home", "listing") has its own
 * version embedded in every key; flush($bucket) swaps it, dropping the whole bucket at once
 * on any cache store. Stale entries age out with their TTL.
 */
class StorefrontCache
{
    public function remember(string $bucket, string $key, int $ttl, Closure $callback): mixed
    {
        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember($this->key($bucket, $key), $ttl, $callback);
    }

    public function flush(string $bucket): void
    {
        Cache::forever($this->versionKey($bucket), Str::random(12));
    }

    public function key(string $bucket, string $key): string
    {
        return 'storefront.'.$bucket.'.'.$this->version($bucket).'.'.$key;
    }

    protected function version(string $bucket): string
    {
        return (string) Cache::rememberForever($this->versionKey($bucket), fn (): string => Str::random(12));
    }

    protected function versionKey(string $bucket): string
    {
        return 'storefront.cache.version.'.$bucket;
    }
}
