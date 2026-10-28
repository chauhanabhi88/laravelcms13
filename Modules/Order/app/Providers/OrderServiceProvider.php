<?php

declare(strict_types=1);

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Order\Console\CancelAbandonedOrdersCommand;
use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Contracts\ShipmentPlanner;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Listeners\CancelOrderOnPaymentFailed;
use Modules\Order\Listeners\ClearCartAfterOrder;
use Modules\Order\Listeners\ConfirmOrderOnCodVerified;
use Modules\Order\Listeners\ConfirmOrderOnPaymentCaptured;
use Modules\Order\Listeners\CreateShipmentTasks;
use Modules\Order\Listeners\RecordOrderLedgerEntries;
use Modules\Order\Listeners\RecordRtoForCodRisk;
use Modules\Order\Listeners\SendOrderCancelledNotification;
use Modules\Order\Listeners\SendOrderPlacedNotifications;
use Modules\Order\Listeners\SwitchOrderToCod;
use Modules\Order\Listeners\UpdateProductSoldCounts;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Policies\SalesOrderPolicy;
use Modules\Order\Repositories\Cache\CacheSalesOrderDecorator;
use Modules\Order\Repositories\Cache\CacheSellerOrderDecorator;
use Modules\Order\Repositories\Eloquent\EloquentSalesOrderRepository;
use Modules\Order\Repositories\Eloquent\EloquentSellerOrderRepository;
use Modules\Order\Repositories\SalesOrderRepository;
use Modules\Order\Repositories\SellerOrderRepository;
use Modules\Order\Services\CodRiskService;
use Modules\Order\Support\NullLedgerPoster;
use Modules\Order\Support\NullShipmentPlanner;
use Modules\Order\Support\OrderAccountDeletionGuard;
use Modules\Order\Support\OrderCustomerHistory;
use Modules\Order\Support\OrderPayableResolver;
use Modules\Payment\Contracts\CustomerOrderHistory;
use Modules\Payment\Contracts\PayableOrderResolver;
use Modules\Payment\Events\CodPaymentConfirmed;
use Modules\Payment\Events\PaymentCaptured;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Events\PaymentSwitchedToCod;
use Modules\Payment\Services\CodEligibilityService;

class OrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);

        $this->registerListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([CancelAbandonedOrdersCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // The Payment and Customer modules bind defaults with bindIf; orders replace them.
        $this->app->bind(PayableOrderResolver::class, OrderPayableResolver::class);
        $this->app->bind(CustomerOrderHistory::class, OrderCustomerHistory::class);
        $this->app->bind(AccountDeletionGuard::class, OrderAccountDeletionGuard::class);

        // Logistics (M13) and Finance (M17) bind the real implementations.
        $this->app->bindIf(ShipmentPlanner::class, NullShipmentPlanner::class);
        $this->app->bindIf(LedgerPoster::class, NullLedgerPoster::class);

        // FR-ORD-009 rules on top of the Payment module's COD eligibility checks.
        $this->app->tag([CodRiskService::class], CodEligibilityService::RISK_CHECK_TAG);
    }

    protected function registerListeners(): void
    {
        Event::listen(OrderPlaced::class, ClearCartAfterOrder::class);

        Event::listen(OrderConfirmed::class, SendOrderPlacedNotifications::class);
        Event::listen(OrderConfirmed::class, CreateShipmentTasks::class);
        Event::listen(OrderConfirmed::class, RecordOrderLedgerEntries::class);

        Event::listen(OrderCancelled::class, SendOrderCancelledNotification::class);

        Event::listen(SellerOrderStatusChanged::class, UpdateProductSoldCounts::class);
        Event::listen(SellerOrderStatusChanged::class, RecordRtoForCodRisk::class);

        Event::listen(PaymentCaptured::class, ConfirmOrderOnPaymentCaptured::class);
        Event::listen(CodPaymentConfirmed::class, ConfirmOrderOnCodVerified::class);
        Event::listen(PaymentFailed::class, CancelOrderOnPaymentFailed::class);
        Event::listen(PaymentSwitchedToCod::class, SwitchOrderToCod::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/order.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'order');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/order'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'order');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/order');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'order');
    }

    /**
     * Admin grid repositories, wrapped in the (pass-through) cache decorators when the
     * module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(SalesOrderRepository::class, function () {
            $repository = new EloquentSalesOrderRepository(new SalesOrder);

            return getModule('order', 'cache') ? new CacheSalesOrderDecorator($repository) : $repository;
        });

        $this->app->bind(SellerOrderRepository::class, function () {
            $repository = new EloquentSellerOrderRepository(new SellerOrder);

            return getModule('order', 'cache') ? new CacheSellerOrderDecorator($repository) : $repository;
        });
    }
}
