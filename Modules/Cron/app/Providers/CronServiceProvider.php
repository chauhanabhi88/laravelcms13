<?php

namespace Modules\Cron\Providers;

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\ServiceProvider;
use Modules\Attribute\Console\TestCommand;
use Modules\Banner\Console\Make;
use Modules\Core\Console\Deletetempimage;
use Modules\Core\Console\Logdelete;
use Modules\Cron\Console\Kernel as CronKernel;
use Modules\Cron\Models\Cron;
use Modules\Cron\Models\CronSchedule;
use Modules\Cron\Repositories\Cache\CacheCronDecorator;
use Modules\Cron\Repositories\Cache\CacheCronScheduleDecorator;
use Modules\Cron\Repositories\Eloquent\EloquentCronRepository;
use Modules\Cron\Repositories\Eloquent\EloquentCronScheduleRepository;
use Modules\Mail\Console\Clearlogs;

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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->commands([
            TestCommand::class,
            Make::class,
            Clearlogs::class,
            Logdelete::class,
            Deletetempimage::class,
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // $this->app->make(Kernel::class);
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
            $sourcePath => $viewPath,
        ], 'views');

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
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'cron');
        }
    }

    private function registerBindings()
    {
        $isEnableCache = getModule('cron', 'cache');

        $this->app->bind(
            'Modules\Cron\Repositories\CronRepository',
            function () use ($isEnableCache) {
                $repository = new EloquentCronRepository(new Cron);

                if (! $isEnableCache) {
                    return $repository;
                }

                return new CacheCronDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Cron\Repositories\CronScheduleRepository',
            function () use ($isEnableCache) {
                $repository = new EloquentCronScheduleRepository(new CronSchedule);

                if (! $isEnableCache) {
                    return $repository;
                }

                return new CacheCronScheduleDecorator($repository);
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
