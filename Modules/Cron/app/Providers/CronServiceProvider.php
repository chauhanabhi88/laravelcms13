<?php

namespace Modules\Cron\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cron\Sidebar\MenuSidebar;
use Illuminate\Console\Scheduling\Schedule;
//use Modules\Cron\Console\Kernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Modules\Cron\Console\Kernel as CronKernel;

class CronServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/Migrations');
        $this->commands([
			\Modules\Attribute\Console\TestCommand::class,
			\Modules\Banner\Console\Make::class,
			\Modules\Mail\Console\Clearlogs::class,
			\Modules\Mail\Console\Clearlogs::class,
			\Modules\Core\Console\Logdelete::class,
			\Modules\Core\Console\Deletetempimage::class,
			\Modules\Cron\Console\Test::class,
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //$this->app->make(Kernel::class);
        $this->app->singleton(ConsoleKernelContract::class, CronKernel::class);
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
            __DIR__.'/../../config/config.php' => config_path('modules/cron.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'cron'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/cron');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'cron');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/cron');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'cron');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'cron');
        }
    }

    private function registerBindings()
    {
        $isEnableCache = getModule("cron", "cache");

        $this->app->bind(
            'Modules\Cron\Repositories\CronRepository',
            function () use ($isEnableCache) {
                $repository = new \Modules\Cron\Repositories\Eloquent\EloquentCronRepository(new \Modules\Cron\Models\Cron());

                if (!$isEnableCache) {
                    return $repository;
                }
                return new \Modules\Cron\Repositories\Cache\CacheCronDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Cron\Repositories\CronScheduleRepository',
            function () use ($isEnableCache) {
                $repository = new \Modules\Cron\Repositories\Eloquent\EloquentCronScheduleRepository(new \Modules\Cron\Models\CronSchedule());

                if (!$isEnableCache) {
                    return $repository;
                }
                return new \Modules\Cron\Repositories\Cache\CacheCronScheduleDecorator($repository);
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
