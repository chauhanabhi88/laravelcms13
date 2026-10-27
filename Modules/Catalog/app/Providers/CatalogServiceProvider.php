<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Listeners\FlushCatalogCaches;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Repositories\Cache\CacheCatalogBrandDecorator;
use Modules\Catalog\Repositories\Cache\CacheCatalogCategoryAttributeDecorator;
use Modules\Catalog\Repositories\Cache\CacheCatalogCategoryDecorator;
use Modules\Catalog\Repositories\Cache\CacheCatalogSizeChartDecorator;
use Modules\Catalog\Repositories\CatalogBrandRepository;
use Modules\Catalog\Repositories\CatalogCategoryAttributeRepository;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Catalog\Repositories\Eloquent\EloquentCatalogBrandRepository;
use Modules\Catalog\Repositories\Eloquent\EloquentCatalogCategoryAttributeRepository;
use Modules\Catalog\Repositories\Eloquent\EloquentCatalogCategoryRepository;
use Modules\Catalog\Repositories\Eloquent\EloquentCatalogSizeChartRepository;
use Modules\Catalog\Support\CatalogCache;
use Modules\Catalog\Support\CatalogMegaMenuProvider;
use Modules\Catalog\Support\NullCategoryInUse;
use Modules\Theme\Contracts\MegaMenuProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Event::listen(CatalogTreeChanged::class, FlushCatalogCaches::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // Theme binds a null provider with bindIf; this binding wins whichever module registers first.
        $this->app->bind(MegaMenuProvider::class, CatalogMegaMenuProvider::class);

        // The Product module (M04) rebinds this to report categories that hold products.
        $this->app->bindIf(CategoryInUse::class, NullCategoryInUse::class);
        $this->app->singletonIf(CatalogCache::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/catalog.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'catalog');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/catalog'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'catalog');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/catalog');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'catalog');
    }

    /**
     * Repositories bound to Eloquent, wrapped in the cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $repositories = [
            CatalogCategoryRepository::class => [EloquentCatalogCategoryRepository::class, CatalogCategory::class, CacheCatalogCategoryDecorator::class],
            CatalogBrandRepository::class => [EloquentCatalogBrandRepository::class, CatalogBrand::class, CacheCatalogBrandDecorator::class],
            CatalogSizeChartRepository::class => [EloquentCatalogSizeChartRepository::class, CatalogSizeChart::class, CacheCatalogSizeChartDecorator::class],
            CatalogCategoryAttributeRepository::class => [EloquentCatalogCategoryAttributeRepository::class, CatalogCategoryAttribute::class, CacheCatalogCategoryAttributeDecorator::class],
        ];

        foreach ($repositories as $contract => [$eloquent, $model, $decorator]) {
            $this->app->bind($contract, function () use ($eloquent, $model, $decorator) {
                $repository = new $eloquent(new $model);

                if (! getModule('catalog', 'cache')) {
                    return $repository;
                }

                return new $decorator($repository);
            });
        }
    }
}
