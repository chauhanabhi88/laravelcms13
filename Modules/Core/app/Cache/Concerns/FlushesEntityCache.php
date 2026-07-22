<?php

namespace Modules\Core\Cache\Concerns;

use Illuminate\Cache\Repository;
use Modules\Core\Cache\FileStore;

/**
 * Flushes every cache entry belonging to a single entity.
 *
 * The strategy depends on what the active store can actually do:
 *  - tag aware stores (redis, memcached, array) flush by tag;
 *  - the module FileStore deletes the entity's cache directory;
 *  - anything else (database, the framework's own file store) has no way to
 *    flush a subset, so entries are left to expire by TTL rather than either
 *    throwing or wiping the whole application cache.
 */
trait FlushesEntityCache
{
    /**
     * Flush all cached entries for the given entity.
     *
     * @param  string  $entityName
     * @return bool
     */
    protected function flushCacheFor($entityName): bool
    {
        if (empty($entityName)) {
            return false;
        }

        $cache = $this->cacheRepository();
        $store = $cache->getStore();

        if (method_exists($store, 'tags')) {
            return (bool) $cache->tags($entityName)->flush();
        }

        if ($store instanceof FileStore) {
            return (bool) $store->flushModuleCache($this->entityCacheKeyPrefix($entityName));
        }

        return false;
    }

    /**
     * The cache repository to flush.
     *
     * Prefers the instance's own repository (the decorator caches reads through
     * it) so the flush always targets the store the entries were written to,
     * and only falls back to the container for classes that hold no repository
     * of their own.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function cacheRepository(): Repository
    {
        if (isset($this->cache) && $this->cache instanceof Repository) {
            return $this->cache;
        }

        return app(Repository::class);
    }

    /**
     * The key prefix identifying an entity's bucket.
     *
     * FileStore::path() matches the segment between the first two colons to
     * decide which directory a key lives in, so the entity name must stay
     * there and stay first.
     *
     * @param  string  $entityName
     * @return string
     */
    protected function entityCacheKeyPrefix($entityName): string
    {
        return sprintf('modules -entity:%s:', $entityName);
    }
}
