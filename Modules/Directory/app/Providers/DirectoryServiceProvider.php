<?php

namespace Modules\Directory\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Directory\Models\DirectoryCountry;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryCurrencyRate;
use Modules\Directory\Models\DirectoryCurrencySetup;
use Modules\Directory\Repositories\Cache\CacheDirectoryCountryCityDecorator;
use Modules\Directory\Repositories\Cache\CacheDirectoryCountryDecorator;
use Modules\Directory\Repositories\Cache\CacheDirectoryCountryStateDecorator;
use Modules\Directory\Repositories\Cache\CacheDirectoryCurrencyRateDecorator;
use Modules\Directory\Repositories\Cache\CacheDirectoryCurrencySetupDecorator;
use Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryCityRepository;
use Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryRepository;
use Modules\Directory\Repositories\Eloquent\EloquentDirectoryCountryStateRepository;
use Modules\Directory\Repositories\Eloquent\EloquentDirectoryCurrencyRateRepository;
use Modules\Directory\Repositories\Eloquent\EloquentDirectoryCurrencySetupRepository;

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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
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
            __DIR__.'/../../config/config.php' => config_path('modules/directory.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php',
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

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
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
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'directory');
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
                $repository = new EloquentDirectoryCurrencySetupRepository(new DirectoryCurrencySetup);

                if (! getModule('directory', 'cache')) {
                    return $repository;
                }

                return new CacheDirectoryCurrencySetupDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCurrencyRateRepository',
            function () {
                $repository = new EloquentDirectoryCurrencyRateRepository(new DirectoryCurrencyRate);

                if (! getModule('directory', 'cache')) {
                    return $repository;
                }

                return new CacheDirectoryCurrencyRateDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryRepository',
            function () {
                $repository = new EloquentDirectoryCountryRepository(new DirectoryCountry);

                if (! getModule('directory', 'cache')) {
                    return $repository;
                }

                return new CacheDirectoryCountryDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryCityRepository',
            function () {
                $repository = new EloquentDirectoryCountryCityRepository(new DirectoryCountryCity);

                if (! getModule('directory', 'cache')) {
                    return $repository;
                }

                return new CacheDirectoryCountryCityDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Directory\Repositories\DirectoryCountryStateRepository',
            function () {
                $repository = new EloquentDirectoryCountryStateRepository(new DirectoryCountryState);

                if (! getModule('directory', 'cache')) {
                    return $repository;
                }

                return new CacheDirectoryCountryStateDecorator($repository);
            }
        );
    }
}
