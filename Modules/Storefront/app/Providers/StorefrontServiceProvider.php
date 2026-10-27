<?php

declare(strict_types=1);

namespace Modules\Storefront\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductBlocked;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerSuspended;
use Modules\Storefront\Console\AggregateSearchTermsCommand;
use Modules\Storefront\Console\ReindexProductsCommand;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Listeners\IndexProductOnChange;
use Modules\Storefront\Listeners\RemoveProductFromIndex;
use Modules\Storefront\Listeners\SyncCategoryPaths;
use Modules\Storefront\Listeners\SyncSellerVisibility;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Repositories\Cache\CacheHomeSectionDecorator;
use Modules\Storefront\Repositories\Cache\CacheProductCollectionDecorator;
use Modules\Storefront\Repositories\Eloquent\EloquentHomeSectionRepository;
use Modules\Storefront\Repositories\Eloquent\EloquentProductCollectionRepository;
use Modules\Storefront\Repositories\HomeSectionRepository;
use Modules\Storefront\Repositories\ProductCollectionRepository;
use Modules\Storefront\Services\Search\ProductSearch;
use Modules\Storefront\Support\StorefrontCache;

class StorefrontServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Search index maintenance (FR-PRD-011). Registered after the Product module's own
        // seller listeners (higher module priority), which update product.seller_active first.
        Event::listen([ProductStatusChanged::class, ProductApproved::class, ProductAggregatesChanged::class], IndexProductOnChange::class);
        Event::listen(ProductBlocked::class, RemoveProductFromIndex::class);
        Event::listen([SellerSuspended::class, SellerReactivated::class], SyncSellerVisibility::class);
        Event::listen(CatalogTreeChanged::class, SyncCategoryPaths::class);

        RateLimiter::for('storefront-autocomplete', fn (Request $request): Limit => Limit::perSecond(30, 10)->by($request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([ReindexProductsCommand::class, AggregateSearchTermsCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        $this->app->singleton(StorefrontCache::class);
        $this->app->bindIf(ProductSearchEngine::class, ProductSearch::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/storefront.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'storefront');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/storefront'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'storefront');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/storefront');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'storefront');
    }

    /**
     * Repositories bound to Eloquent, wrapped in the cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $repositories = [
            HomeSectionRepository::class => [EloquentHomeSectionRepository::class, HomeSection::class, CacheHomeSectionDecorator::class],
            ProductCollectionRepository::class => [EloquentProductCollectionRepository::class, ProductCollection::class, CacheProductCollectionDecorator::class],
        ];

        foreach ($repositories as $contract => [$eloquent, $model, $decorator]) {
            $this->app->bind($contract, function () use ($eloquent, $model, $decorator) {
                $repository = new $eloquent(new $model);

                if (! getModule('storefront', 'cache')) {
                    return $repository;
                }

                return new $decorator($repository);
            });
        }
    }
}
