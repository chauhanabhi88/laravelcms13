<?php

namespace Modules\Pages\Providers;

use Illuminate\Support\ServiceProvider;

class PagesServiceProvider extends ServiceProvider
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
        $this->registerBindings();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Pages\Repositories\PagesRepository',
            function () {
                $repository = new \Modules\Pages\Repositories\Eloquent\EloquentPagesRepository(new \Modules\Pages\Models\Pages());

                if (! getModule("pages", "cache")) {
                    return $repository;
                }
                return new \Modules\Pages\Repositories\Cache\CachePagesDecorator($repository);
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
            __DIR__.'/../../config/config.php' => config_path('modules/pages.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'pages'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/pages');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'pages');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/pages');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'pages');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'pages');
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
