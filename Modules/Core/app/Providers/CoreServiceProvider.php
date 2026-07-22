<?php

namespace Modules\Core\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Cache\FileStore;
use Modules\Core\Commands\Database\SeedCommand;
use Modules\Core\Commands\Make\MigrationMakeCommand;
use Modules\Core\Commands\Make\ModuleMakeCommand;
use Modules\Core\Console\CronMakeCommand;
use Modules\Core\Console\CustomBladeCreateCommand;
use Modules\Core\Console\CustomBladeEditCommand;
use Modules\Core\Console\CustomBladeGridCommand;
use Modules\Core\Console\CustomBladeIndexCommand;
use Modules\Core\Console\CustomCacheCommand;
use Modules\Core\Console\CustomControllerCommand;
use Modules\Core\Console\CustomCreateRequestCommand;
use Modules\Core\Console\CustomEloquentCommand;
use Modules\Core\Console\CustomEmptyCacheCommand;
use Modules\Core\Console\CustomEmptyEloquentCommand;
use Modules\Core\Console\CustomEntityCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeCreateCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeCreateTranslatableCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeEditCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeEditTranslatableCommand;
use Modules\Core\Console\CustomFolderTranslatableControllerCommand;
use Modules\Core\Console\CustomFolderTranslatableCreateRequestCommand;
use Modules\Core\Console\CustomFolderTranslatableUpdateRequestCommand;
use Modules\Core\Console\CustomLangCommand;
use Modules\Core\Console\CustomRepositoryCommand;
use Modules\Core\Console\CustomTranslatableBladeCreateCommand;
use Modules\Core\Console\CustomTranslatableBladeCreateTranslatableCommand;
use Modules\Core\Console\CustomTranslatableBladeEditCommand;
use Modules\Core\Console\CustomTranslatableBladeEditTranslatableCommand;
use Modules\Core\Console\CustomTranslatableControllerCommand;
use Modules\Core\Console\CustomTranslatableCreateRequestCommand;
use Modules\Core\Console\CustomTranslatableEntityCommand;
use Modules\Core\Console\CustomTranslatableUpdateRequestCommand;
use Modules\Core\Console\CustomUpdateRequestCommand;
use Modules\Core\Foundations\AssetsManager as AssetsManagerInterFace;
use Modules\Core\Foundations\Modules\AssetsManager;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * The filters base class name.
     *
     * @var array
     */
    protected $middleware = [
        'Core' => [
            'can' => 'Authorization',
            'locale' => 'Localization',
            'auth' => 'Authenticate',
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
        $this->warnOnUnflushableCacheStore();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->commands([
            CronMakeCommand::class,
            CustomEntityCommand::class,
            CustomRepositoryCommand::class,
            CustomEloquentCommand::class,
            CustomCacheCommand::class,
            CustomLangCommand::class,
            CustomCreateRequestCommand::class,
            CustomUpdateRequestCommand::class,
            CustomControllerCommand::class,
            CustomBladeIndexCommand::class,
            CustomBladeCreateCommand::class,
            CustomBladeEditCommand::class,
            CustomFolderTranslatableBladeCreateCommand::class,
            CustomFolderTranslatableBladeEditCommand::class,
            CustomFolderTranslatableBladeCreateTranslatableCommand::class,
            CustomFolderTranslatableBladeEditTranslatableCommand::class,
            CustomBladeGridCommand::class,
            CustomEmptyEloquentCommand::class,
            CustomEmptyCacheCommand::class,
            CustomTranslatableEntityCommand::class,
            CustomFolderTranslatableControllerCommand::class,
            CustomTranslatableControllerCommand::class,
            CustomTranslatableCreateRequestCommand::class,
            CustomTranslatableUpdateRequestCommand::class,
            CustomFolderTranslatableCreateRequestCommand::class,
            CustomFolderTranslatableUpdateRequestCommand::class,
            CustomTranslatableBladeCreateCommand::class,
            CustomTranslatableBladeCreateTranslatableCommand::class,
            CustomTranslatableBladeEditCommand::class,
            CustomTranslatableBladeEditTranslatableCommand::class,
            MigrationMakeCommand::class,
            ModuleMakeCommand::class,
            // Registering last wins in Artisan's registry, and that - not the
            // module.json "aliases" block - is what actually replaces nwidart's
            // commands. nwidart registers its own commands before it registers
            // module aliases, so the vendor class is already loaded by then and
            // class_alias() is silently skipped. module:seed ran the vendor
            // implementation until this line was added.
            SeedCommand::class,
        ]);
    }

    /**
     * Register the filters.
     *
     * @return void
     */
    public function registerMiddleware(Router $router)
    {
        if ((! config('core.translation')) && (! config('core.translation_front')) && (! config('core.translation_api'))) {
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
            return new AssetsManager;
        });
        $this->app->register(RouteServiceProvider::class);
        // The nwidart aliases live in module.json's "aliases" block, which
        // nwidart applies in Module::register() - before this provider runs.
        // Duplicating them here only gave two places to keep in sync.
        $this->registerCacheStore();
    }

    /**
     * Bind Core's FileStore as the "file" cache driver.
     *
     * This used to be a class_alias over Illuminate\Cache\FileStore, which only
     * took effect if Core registered before anything autoloaded the real class.
     * When it lost that race the framework store was used instead and every
     * flushCacheFor() silently became a no-op. Cache::extend has no such race.
     *
     * @return void
     */
    protected function registerCacheStore()
    {
        $this->app->booting(function ($app) {
            $app['cache']->extend('file', function ($app, $config) {
                return $app['cache']->repository(
                    (new FileStore(
                        $app['files'],
                        $config['path'],
                        $config['permission'] ?? null,
                        $app['config']['cache.serializable_classes'] ?? null,
                    ))->setLockDirectory($config['lock_path'] ?? null),
                    $config
                );
            });
        });
    }

    /**
     * Warn when the active cache store cannot flush a single entity.
     *
     * Per-entity invalidation needs either tag support or Core's FileStore.
     * config/cache.php defaults to the database driver, which has neither, so
     * an environment that forgets CACHE_STORE=file would serve stale reads
     * until their TTL expired with nothing in the logs to explain it.
     *
     * @return void
     */
    protected function warnOnUnflushableCacheStore()
    {
        $store = $this->app['cache']->store()->getStore();

        if (method_exists($store, 'tags') || $store instanceof FileStore) {
            return;
        }

        logger()->warning(sprintf(
            'Cache store [%s] supports neither tags nor per-entity flushing, so Modules\\Core '
            .'cannot invalidate an entity on write - cached reads will be stale until they expire. '
            .'Use a tag-aware store (redis, memcached, array) or set CACHE_STORE=file.',
            $store::class
        ));
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/core.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php',
            'core'
        );

        $this->publishes([
            __DIR__.'/../../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/translatable.php',
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

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
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
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'core');
        }
    }
}
