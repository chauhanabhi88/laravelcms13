<?php

namespace Modules\Directory\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Directory\Sidebar\MenuSidebar;

class DirectoryServiceProvider extends ServiceProvider
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
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/Migrations');
        $this->registerBindings();
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
            __DIR__ . '/../../config/config.php' => config_path('modules/directory.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/config.php',
            'directory'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/directory');

        $sourcePath = __DIR__ . '/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'directory');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/directory');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'directory');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'directory');
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

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCurrencySetupRepository',
            function () {
                $repository = new \Modules\Directory\Repositories\Eloquent\EloquentDirectoryCurrencySetupRepository(new \Modules\Directory\Models\DirectoryCurrencySetup());

                if (!getModule("directory", "cache")) {
                    return $repository;
                }

                return new \Modules\Directory\Repositories\Cache\CacheDirectoryCurrencySetupDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCurrencyRateRepository',
            function () {
                $repository = new \Modules\Directory\Repositories\Eloquent\EloquentDirectoryCurrencyRateRepository(new \Modules\Directory\Models\DirectoryCurrencyRate());

                if (!getModule("directory", "cache")) {
                    return $repository;
                }

                return new \Modules\Directory\Repositories\Cache\CacheDirectoryCurrencyRateDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryRepository',
            function () {
                $repository = new \Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryRepository(new \Modules\Directory\Models\DirectoryCountry());

                if (!getModule("directory", "cache")) {
                    return $repository;
                }

                return new \Modules\Directory\Repositories\Cache\CacheDirectoryCountryDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryCityRepository',
            function () {
                $repository = new \Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryCityRepository(new \Modules\Directory\Models\DirectoryCountryCity());

                if (!getModule("directory", "cache")) {
                    return $repository;
                }

                return new \Modules\Directory\Repositories\Cache\CacheDirectoryCountryCityDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryStateRepository',
            function () {
                $repository = new \Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryStateRepository(new \Modules\Directory\Models\DirectoryCountryState());

                if (!getModule("directory", "cache")) {
                    return $repository;
                }

                return new \Modules\Directory\Repositories\Cache\CacheDirectoryCountryStateDecorator($repository);
            }
        );
    }
}
