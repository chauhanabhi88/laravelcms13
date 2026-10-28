<?php

declare(strict_types=1);

namespace Modules\Cart\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Cart\Adjustments\CodFeeAdjustment;
use Modules\Cart\Adjustments\DeliveryChargeAdjustment;
use Modules\Cart\Console\PruneCartsCommand;
use Modules\Cart\Console\SendAbandonedCartRemindersCommand;
use Modules\Cart\Listeners\MergeGuestCartOnLogin;
use Modules\Cart\Models\Cart;
use Modules\Cart\Repositories\Cache\CacheCartDecorator;
use Modules\Cart\Repositories\CartRepository;
use Modules\Cart\Repositories\Eloquent\EloquentCartRepository;
use Modules\Cart\Services\CartTotals;
use Modules\Cart\Support\StorefrontCartEntryPoint;
use Modules\Cart\Support\ThemeCartCount;
use Modules\Customer\Events\CustomerLoggedIn;
use Modules\Storefront\Contracts\CartEntryPoint;
use Modules\Theme\Contracts\CartCountProvider;

class CartServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Event::listen(CustomerLoggedIn::class, MergeGuestCartOnLogin::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendAbandonedCartRemindersCommand::class, PruneCartsCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // The Storefront and Theme bind null implementations with bindIf; these replace them.
        $this->app->bind(CartEntryPoint::class, StorefrontCartEntryPoint::class);
        $this->app->bind(CartCountProvider::class, ThemeCartCount::class);

        // Price-summary pipeline, in this order. The Promotion module (M18) tags its coupon step.
        $this->app->tag([DeliveryChargeAdjustment::class, CodFeeAdjustment::class], CartTotals::ADJUSTMENT_TAG);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/cart.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'cart');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/cart'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'cart');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/cart');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'cart');
    }

    /**
     * The admin grid repository, wrapped in the (pass-through) cache decorator when the
     * module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(CartRepository::class, function () {
            $repository = new EloquentCartRepository(new Cart);

            if (! getModule('cart', 'cache')) {
                return $repository;
            }

            return new CacheCartDecorator($repository);
        });
    }
}
