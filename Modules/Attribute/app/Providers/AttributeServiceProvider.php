<?php

namespace Modules\Attribute\Providers;

use Illuminate\Support\ServiceProvider;

class AttributeServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
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
            __DIR__.'/../../config/config.php' => config_path('modules/attribute.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'attribute'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/attribute');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'attribute');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/attribute');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'attribute');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'attribute');
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
            'Modules\Attribute\Repositories\AttributeRepository',
            function () {
                $repository = new \Modules\Attribute\Repositories\Eloquent\EloquentAttributeRepository(new \Modules\Attribute\Models\Attribute());

                if (! getModule("attribute", "cache")) {
                    return $repository;
                }

                return new \Modules\Attribute\Repositories\Cache\CacheAttributeDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Attribute\Repositories\AttributeOptionRepository',
            function () {
                $repository = new \Modules\Attribute\Repositories\Eloquent\EloquentAttributeOptionRepository(new \Modules\Attribute\Models\AttributeOption());

                if (! getModule("attribute", "cache")) {
                    return $repository;
                }

                return new \Modules\Attribute\Repositories\Cache\CacheAttributeOptionDecorator($repository);
            }
        );
    }
}
