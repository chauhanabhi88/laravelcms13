<?php

namespace Modules\Mail\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Mail\Sidebar\MenuSidebar;

class MailServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/Migrations');
    }
    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Mail\Repositories\MailRepository',
            function () {
                $repository = new \Modules\Mail\Repositories\Eloquent\EloquentMailRepository(new \Modules\Mail\Models\MailTemplate());

                if (! getModule("mail", "cache")) {
                    return $repository;
                }

                return new \Modules\Mail\Repositories\Cache\CacheMailDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Mail\Repositories\MailLogRepository',
            function () {
                $repository = new \Modules\Mail\Repositories\Eloquent\EloquentMailLogRepository(new \Modules\Mail\Models\MailLog());

                if (! getModule("mail", "cache")) {
                    return $repository;
                }

                return new \Modules\Mail\Repositories\Cache\CacheMailLogDecorator($repository);
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
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/mail.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'mail'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/mail');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'mail');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/mail');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'mail');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'mail');
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
