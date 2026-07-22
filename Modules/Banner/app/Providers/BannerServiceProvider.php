<?php

namespace Modules\Banner\Providers;

use Illuminate\Support\ServiceProvider;

class BannerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerBindings();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
    
    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Banner\Repositories\BannerRepository',
            function () {
                $repository = new \Modules\Banner\Repositories\Eloquent\EloquentBannerRepository(new \Modules\Banner\Models\Banner());

                if (! getModule("banner", "cache")) {
                    return $repository;
                }

                return new \Modules\Banner\Repositories\Cache\CacheBannerDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Banner\Repositories\BannerGroupRepository',
            function () {
                $repository = new \Modules\Banner\Repositories\Eloquent\EloquentBannerGroupRepository(new \Modules\Banner\Models\BannerGroup());
                
                if (! getModule("banner", "cache")) {
                    return $repository;
                }

                return new \Modules\Banner\Repositories\Cache\CacheBannerGroupDecorator($repository);
            }
        );
    }


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/banner.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'banner'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/banner');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'banner');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/banner');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'banner');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'banner');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
