<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customer\Http\Middleware\Backend;
use Modules\Customer\Sidebar\MenuSidebar;

class CustomerServiceProvider extends ServiceProvider
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
            'Modules\Customer\Repositories\CustomerRepository',
            function () {
                $repository = new \Modules\Customer\Repositories\Eloquent\EloquentCustomerRepository(new \Modules\Customer\Models\Customer());

                if (! getModule("customer", "cache")) {
                    return $repository;
                }

                return new \Modules\Customer\Repositories\Cache\CacheCustomerDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Customer\Repositories\DeletedCustomerRepository',
            function () {
                $repository = new \Modules\Customer\Repositories\Eloquent\EloquentDeletedCustomerRepository(new \Modules\Customer\Models\Customer());

                if (! getModule("customer", "cache")) {
                    return $repository;
                }

                return new \Modules\Customer\Repositories\Cache\CacheDeletedCustomerDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Customer\Repositories\CustomerGroupRepository',
            function () {
                $repository = new \Modules\Customer\Repositories\Eloquent\EloquentCustomerGroupRepository(new \Modules\Customer\Models\CustomerGroup());

                if (! getModule("customer", "cache")) {
                    return $repository;
                }

                return new \Modules\Customer\Repositories\Cache\CacheCustomerGroupDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Customer\Repositories\CustomerOnlineOfflineLogRepository',
            function () {
                $repository = new \Modules\Customer\Repositories\Eloquent\EloquentCustomerOnlineOfflineLogRepository(new \Modules\Customer\Models\Customer());

                if (! getModule("customer", "cache")) {
                    return $repository;
                }

                return new \Modules\Customer\Repositories\Cache\CacheCustomerOnlineOfflineLogDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Customer\Repositories\CustomerLoginLogRepository',
            function () {
                $repository = new \Modules\Customer\Repositories\Eloquent\EloquentCustomerLoginLogRepository(new \Modules\Customer\Models\CustomerLoginLog());

                if (! getModule("customer", "cache")) {
                    return $repository;
                }

                return new \Modules\Customer\Repositories\Cache\CacheCustomerLoginLogDecorator($repository);
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
            __DIR__.'/../../config/config.php' => config_path('modules/customer.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'customer'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/customer');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'customer');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/customer');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'customer');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'customer');
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
