<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ProvidesAppConfig;
use Modules\Customer\Console\ProcessAccountDeletionsCommand;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerGroup;
use Modules\Customer\Models\CustomerLoginLog;
use Modules\Customer\Repositories\Cache\CacheCustomerDecorator;
use Modules\Customer\Repositories\Cache\CacheCustomerGroupDecorator;
use Modules\Customer\Repositories\Cache\CacheCustomerLoginLogDecorator;
use Modules\Customer\Repositories\Cache\CacheCustomerOnlineOfflineLogDecorator;
use Modules\Customer\Repositories\Cache\CacheDeletedCustomerDecorator;
use Modules\Customer\Repositories\Eloquent\EloquentCustomerGroupRepository;
use Modules\Customer\Repositories\Eloquent\EloquentCustomerLoginLogRepository;
use Modules\Customer\Repositories\Eloquent\EloquentCustomerOnlineOfflineLogRepository;
use Modules\Customer\Repositories\Eloquent\EloquentCustomerRepository;
use Modules\Customer\Repositories\Eloquent\EloquentDeletedCustomerRepository;
use Modules\Customer\Support\CustomerAppConfig;
use Modules\Customer\Support\NullAccountDeletionGuard;
use Modules\Customer\Support\StorefrontWishlistProvider;
use Modules\Customer\Support\ThemeWishlistProvider;
use Modules\Storefront\Contracts\WishlistStateProvider;
use Modules\Theme\Contracts\WishlistProvider;

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
        $this->loadMigrationsFrom(__DIR__.'/../../database/Migrations');
    }

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Customer\Repositories\CustomerRepository',
            function () {
                $repository = new EloquentCustomerRepository(new Customer);

                if (! getModule('customer', 'cache')) {
                    return $repository;
                }

                return new CacheCustomerDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Customer\Repositories\DeletedCustomerRepository',
            function () {
                $repository = new EloquentDeletedCustomerRepository(new Customer);

                if (! getModule('customer', 'cache')) {
                    return $repository;
                }

                return new CacheDeletedCustomerDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Customer\Repositories\CustomerGroupRepository',
            function () {
                $repository = new EloquentCustomerGroupRepository(new CustomerGroup);

                if (! getModule('customer', 'cache')) {
                    return $repository;
                }

                return new CacheCustomerGroupDecorator($repository);
            }
        );
        $this->app->bind(
            'Modules\Customer\Repositories\CustomerOnlineOfflineLogRepository',
            function () {
                $repository = new EloquentCustomerOnlineOfflineLogRepository(new Customer);

                if (! getModule('customer', 'cache')) {
                    return $repository;
                }

                return new CacheCustomerOnlineOfflineLogDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Customer\Repositories\CustomerLoginLogRepository',
            function () {
                $repository = new EloquentCustomerLoginLogRepository(new CustomerLoginLog);

                if (! getModule('customer', 'cache')) {
                    return $repository;
                }

                return new CacheCustomerLoginLogDecorator($repository);
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
        $this->registerMarketplaceBindings();

        $this->app->tag([CustomerAppConfig::class], ProvidesAppConfig::TAG);
    }

    /**
     * M08 extension points. The wishlist providers replace the Theme / Storefront null
     * providers (they use bindIf, so a plain bind wins whatever the module order). Orders
     * (M12) and returns (M15) rebind the deletion guard.
     */
    protected function registerMarketplaceBindings(): void
    {
        if (interface_exists(WishlistProvider::class)) {
            $this->app->bind(WishlistProvider::class, ThemeWishlistProvider::class);
        }

        if (interface_exists(WishlistStateProvider::class)) {
            $this->app->bind(WishlistStateProvider::class, StorefrontWishlistProvider::class);
        }

        $this->app->bindIf(AccountDeletionGuard::class, NullAccountDeletionGuard::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessAccountDeletionsCommand::class]);
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
            $sourcePath => $viewPath,
        ], 'views');

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
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'customer');
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
