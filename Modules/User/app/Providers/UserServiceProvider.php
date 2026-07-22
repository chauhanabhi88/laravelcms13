<?php
namespace Modules\User\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\User\Http\Middleware\Backend;
use Modules\User\Http\Middleware\GuestMiddleware;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * @var array
     */
    protected $middleware = [
        'backend' => Backend::class,
        'auth.guest' => GuestMiddleware::class,
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerMiddleware();
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

    private function registerMiddleware()
    {
        foreach ($this->middleware as $name => $class) {
            $this->app['router']->aliasMiddleware($name, $class);
        }
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/user.php'),
        ], 'config');
        
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'user'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/user');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'user');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/user');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'user');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'user');
        }
    }

    private function registerBindings()
    {
      
        $this->app->bind(
            "Modules\User\Repositories\DeletedUserRepository",
            function () {
                $repository = new \Modules\User\Repositories\Eloquent\EloquentDeletedUserRepository(new \Modules\User\Models\User());

                if (! getModule("user", "cache")) {
                    return $repository;
                }

                return new \Modules\User\Repositories\Cache\CacheDeletedUserDecorator($repository);
            }
        );
            
		  $this->app->bind(
            'Modules\User\Repositories\UserRepository',
            function () {
                $repository = new \Modules\User\Repositories\Eloquent\EloquentUserRepository(new \Modules\User\Models\User());

                if (! getModule("user", "cache")) {
                    return $repository;
                }

                return new \Modules\User\Repositories\Cache\CacheUserDecorator($repository);
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
