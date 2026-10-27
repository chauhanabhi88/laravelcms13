<?php

declare(strict_types=1);

namespace Modules\Catalog\Listeners;

use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Support\CatalogCache;
use Modules\Core\Cache\Concerns\FlushesEntityCache;
use Modules\Theme\Support\MegaMenu;

/**
 * Drops everything derived from the taxonomy: the repository read cache, the tree /
 * defaults / attribute caches and the storefront mega-menu.
 */
class FlushCatalogCaches
{
    use FlushesEntityCache;

    public function __construct(protected CatalogCache $cache) {}

    public function handle(CatalogTreeChanged $event): void
    {
        $this->flushCacheFor(config('catalog.cache.category'));
        $this->flushCacheFor(config('catalog.cache.category_attribute'));
        $this->cache->flush();

        if (class_exists(MegaMenu::class) && app()->bound(MegaMenu::class)) {
            app(MegaMenu::class)->flush();
        }
    }
}
