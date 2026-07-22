<?php

namespace Modules\Language\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Language\Sidebar\MenuSidebar;

class LanguageServiceProvider extends ServiceProvider
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
            __DIR__ . '/../../config/config.php' => config_path('modules/language.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/config.php',
            'language'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/language');

        $sourcePath = __DIR__ . '/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'language');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/language');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'language');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'language');
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
            'Modules\Language\Repositories\LanguageRepository',
            function () {
                $repository = new \Modules\Language\Repositories\Eloquent\EloquentLanguageRepository(new \Modules\Language\Models\Language());

                if (!getModule("language", "cache")) {
                    return $repository;
                }

                return new \Modules\Language\Repositories\Cache\CacheLanguageDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Language\Repositories\TranslationRepository',
            function () {
                $repository = new \Modules\Language\Repositories\Eloquent\EloquentTranslationRepository(new \Modules\Language\Models\Language());

                if (!getModule("language", "cache")) {
                    return $repository;
                }

                return new \Modules\Language\Repositories\Cache\CacheTranslationDecorator($repository);
            }
        );
    }

}
