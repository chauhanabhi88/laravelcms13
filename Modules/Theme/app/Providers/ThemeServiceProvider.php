<?php

namespace Modules\Theme\Providers;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
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
            __DIR__.'/../../config/config.php' => config_path('modules/theme.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'theme'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/theme');
        
        $sourcePath = __DIR__.'/../../resources/views';
        
        $this->publishes([
            $sourcePath => $viewPath
        ],'views');
        
        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'theme');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/theme');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'theme');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'theme');
        }
    }

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Theme\Repositories\ThemeRepository',
            function () {
                $repository = new \Modules\Theme\Repositories\Eloquent\EloquentThemeRepository(new \Modules\Theme\Models\Theme());

                if (! getModule("theme", "cache")) {
                    return $repository;
                }

                return new \Modules\Theme\Repositories\Cache\CacheThemeDecorator($repository);
            }
        );
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
