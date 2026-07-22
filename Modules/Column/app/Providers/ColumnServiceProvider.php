<?php

namespace Modules\Column\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ColumnServiceProvider extends ServiceProvider
{
    use PathNamespace;
    /**
     * Indicates if loading of the provider is deferred.
     *
     */
    protected $defer = false;

    protected string $name = 'Column';

    protected string $nameLower = 'column';

    /**
     * Boot the application events.
     *
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->registerBindings();
    }

    /**
     * Register the service provider.
     *
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register config.
     *
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/column.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'column'
        );
    }

    /**
     * Register views.
     *
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        //$this->loadViewsFrom(array_merge(array_map(function ($path) {
        //    return $path . '/modules/column';
        //}, \Config::get('view.paths')), [$sourcePath]), 'column');

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    /**
     * Register translations.
     *
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            //$this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', $this->nameLower);
            //$this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            //$this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Get the services provided by the provider.
     *
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Column\Repositories\ColumnRepository',
            function () {
                $repository = new \Modules\Column\Repositories\Eloquent\EloquentColumnRepository(new \Modules\Column\Models\Column());

                if (! getModule("column", "cache")) {
                    return $repository;
                }

                return new \Modules\Column\Repositories\Cache\CacheColumnDecorator($repository);
            }
        );
    }
}