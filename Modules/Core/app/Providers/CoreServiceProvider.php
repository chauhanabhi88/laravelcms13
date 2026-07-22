<?php

namespace Modules\Core\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Foundations\AssetsManager as AssetsManagerInterFace;
use Modules\Core\Foundations\Modules\AssetsManager;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * The filters base class name.
     *
     * @var array
     */
    protected $middleware = [
        'Core' => [
            'can' => 'Authorization',
            'locale' => 'Localization',
            'auth' => 'Authenticate'
        ],
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerMiddleware($this->app['router']);
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->commands([
            \Modules\Core\Console\CronMakeCommand::class,
            \Modules\Core\Console\CustomEntityCommand::class,
            \Modules\Core\Console\CustomRepositoryCommand::class,
            \Modules\Core\Console\CustomEloquentCommand::class,
            \Modules\Core\Console\CustomCacheCommand::class,
            \Modules\Core\Console\CustomLangCommand::class,
            \Modules\Core\Console\CustomCreateRequestCommand::class,
            \Modules\Core\Console\CustomUpdateRequestCommand::class,
            \Modules\Core\Console\CustomControllerCommand::class,
            \Modules\Core\Console\CustomBladeIndexCommand::class,
            \Modules\Core\Console\CustomBladeCreateCommand::class,
            \Modules\Core\Console\CustomBladeEditCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableBladeCreateCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableBladeEditCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableBladeCreateTranslatableCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableBladeEditTranslatableCommand::class,
            \Modules\Core\Console\CustomBladeGridCommand::class,
            \Modules\Core\Console\CustomEmptyEloquentCommand::class,
            \Modules\Core\Console\CustomEmptyCacheCommand::class,
            \Modules\Core\Console\CustomTranslatableEntityCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableControllerCommand::class,
            \Modules\Core\Console\CustomTranslatableControllerCommand::class,
            \Modules\Core\Console\CustomTranslatableCreateRequestCommand::class,
            \Modules\Core\Console\CustomTranslatableUpdateRequestCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableCreateRequestCommand::class,
            \Modules\Core\Console\CustomFolderTranslatableUpdateRequestCommand::class,
            \Modules\Core\Console\CustomTranslatableBladeCreateCommand::class,
            \Modules\Core\Console\CustomTranslatableBladeCreateTranslatableCommand::class,
            \Modules\Core\Console\CustomTranslatableBladeEditCommand::class,
            \Modules\Core\Console\CustomTranslatableBladeEditTranslatableCommand::class,
            \Modules\Core\Commands\Make\MigrationMakeCommand::class,
            \Modules\Core\Commands\Make\ModuleMakeCommand::class,
        ]);
    }

    /**
     * Register the filters.
     *
     * @param  Router $router
     * @return void
     */
    public function registerMiddleware(Router $router)
    {
        if ((!config('core.translation')) && (!config('core.translation_front')) && (!config('core.translation_api'))) {
            unset($this->middleware['Core']['locale']);
        }
        
        foreach ($this->middleware as $module => $middlewares) {
            foreach ($middlewares as $name => $middleware) {
                $class = "Modules\\{$module}\\Http\\Middleware\\{$middleware}";

                $router->aliasMiddleware($name, $class);
            }
        }
        
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->singleton(AssetsManagerInterFace::class, function () {
            return new AssetsManager();
        });
        $this->app->register(RouteServiceProvider::class);
        $loader = AliasLoader::getInstance();
        $loader->alias('Illuminate\Cache\FileStore', 'Modules\Core\Cache\FileStore');
        $loader->alias("Nwidart\Modules\Support\Migrations\SchemaParser", 'Modules\Core\Support\Migrations\SchemaParser');
        $loader->alias("Nwidart\Modules\Commands\Make\MigrationMakeCommand", "Modules\Core\Commands\Make\MigrationMakeCommand");
        $loader->alias("Nwidart\Modules\Generators\ModuleGenerator", "Modules\Core\Generators\ModuleGenerator");
        $loader->alias("Nwidart\Modules\Commands\Make\ModuleMakeCommand", "Modules\Core\Commands\Make\ModuleMakeCommand");
        $loader->alias("Nwidart\Modules\Commands\Database\SeedCommand", "Modules\Core\Commands\Database\SeedCommand");
        $loader->alias("Nwidart\Modules\Commands\Actions\EnableCommand", "Modules\Core\Commands\Actions\EnableCommand");
        $loader->alias("Nwidart\Modules\Commands\Actions\DisableCommand", "Modules\Core\Commands\Actions\DisableCommand");
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('modules/core.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/config.php',
            'core'
        );

        $this->publishes([
            __DIR__ . '/../../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable');
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/translatable.php',
            'translatable'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/core');

        $sourcePath = __DIR__ . '/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'core');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/core');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'core');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'core');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            AuthServiceProvider::class
        ];
    }
}
