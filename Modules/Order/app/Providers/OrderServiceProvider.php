<?php

declare(strict_types=1);

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Order\Composers\SellerDashboardComposer;
use Modules\Order\Console\AutoAcceptOrdersCommand;
use Modules\Order\Console\AutoConfirmReturnReceiptsCommand;
use Modules\Order\Console\CancelAbandonedOrdersCommand;
use Modules\Order\Console\CancelSlaBreachedOrdersCommand;
use Modules\Order\Console\SnapshotSellerQualityCommand;
use Modules\Order\Console\WarnSlaBreachesCommand;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Contracts\OrderReturns;
use Modules\Order\Contracts\OrderReviews;
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
use Modules\Order\Listeners\FlushSellerOrderDeskCounts;
use Modules\Order\Listeners\RecordOrderLedgerEntries;
use Modules\Order\Listeners\RecordRtoForCodRisk;
use Modules\Order\Listeners\SendOrderCancelledNotification;
use Modules\Order\Listeners\SendOrderPlacedNotifications;
use Modules\Order\Listeners\SwitchOrderToCod;
use Modules\Order\Listeners\UpdateProductSoldCounts;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Policies\SalesOrderPolicy;
use Modules\Order\Repositories\Cache\CacheSalesOrderDecorator;
use Modules\Order\Repositories\Cache\CacheSellerOrderDecorator;
use Modules\Order\Repositories\Cache\CacheSellerOrderDeskDecorator;
use Modules\Order\Repositories\Eloquent\EloquentSalesOrderRepository;
use Modules\Order\Repositories\Eloquent\EloquentSellerOrderDeskRepository;
use Modules\Order\Repositories\Eloquent\EloquentSellerOrderRepository;
use Modules\Order\Repositories\SalesOrderRepository;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Repositories\SellerOrderRepository;
use Modules\Order\Services\CodRiskService;
use Modules\Order\Support\NullFulfilmentLogistics;
use Modules\Order\Support\NullLedgerPoster;
use Modules\Order\Support\NullOrderReturns;
use Modules\Order\Support\NullOrderReviews;
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
use Modules\Seller\Policies\SellerOwnedPolicy;

class OrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);

        // Supplier panel tenancy (M14): another seller's order or label batch answers 404.
        Gate::policy(SellerOrder::class, SellerOwnedPolicy::class);
        Gate::policy(LabelBatch::class, SellerOwnedPolicy::class);

        $this->registerListeners();
        $this->registerSellerPanel();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CancelAbandonedOrdersCommand::class,
                AutoAcceptOrdersCommand::class,
                WarnSlaBreachesCommand::class,
                CancelSlaBreachedOrdersCommand::class,
                SnapshotSellerQualityCommand::class,
                AutoConfirmReturnReceiptsCommand::class,
            ]);
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

        // Logistics (M13), Returns (M15), Review (M16) and Finance (M17) bind the real implementations.
        $this->app->bindIf(ShipmentPlanner::class, NullShipmentPlanner::class);
        $this->app->bindIf(LedgerPoster::class, NullLedgerPoster::class);
        $this->app->bindIf(FulfilmentLogistics::class, NullFulfilmentLogistics::class);
        $this->app->bindIf(OrderReturns::class, NullOrderReturns::class);
        $this->app->bindIf(OrderReviews::class, NullOrderReviews::class);

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
        Event::listen(SellerOrderStatusChanged::class, FlushSellerOrderDeskCounts::class);

        Event::listen(PaymentCaptured::class, ConfirmOrderOnPaymentCaptured::class);
        Event::listen(CodPaymentConfirmed::class, ConfirmOrderOnCodVerified::class);
        Event::listen(PaymentFailed::class, CancelOrderOnPaymentFailed::class);
        Event::listen(PaymentSwitchedToCod::class, SwitchOrderToCod::class);
    }

    /**
     * Supplier panel (plan M14 §6/§7): the "Orders" sidebar section after Catalog (Logistics
     * adds "Manifests" to it), real counts on the dashboard KPI tiles and the quality card.
     */
    protected function registerSellerPanel(): void
    {
        $items = [
            ['route' => 'seller.order.index', 'label' => 'order::fulfilment.menu.desk', 'icon' => 'package', 'active' => ['seller.order.*', 'seller.label.*']],
            ['route' => 'seller.settings.orders', 'label' => 'order::fulfilment.menu.settings', 'icon' => 'settings', 'active' => 'seller.settings.orders*'],
        ];

        $menu = (array) config('theme.seller_menu', []);
        $existing = array_filter((array) ($menu['orders']['items'] ?? []), fn (array $item): bool => ! in_array($item['route'] ?? null, array_column($items, 'route'), true));
        $section = ['label' => 'order::fulfilment.menu.orders', 'items' => array_values(array_merge($items, $existing))];
        unset($menu['orders']);

        $ordered = [];

        foreach ($menu as $key => $value) {
            $ordered[$key] = $value;

            if ($key === 'catalog') {
                $ordered['orders'] = $section;
            }
        }

        config(['theme.seller_menu' => isset($ordered['orders']) ? $ordered : ['orders' => $section] + $ordered]);

        $widgets = (array) config('seller.dashboard_widgets', []);

        if (! in_array('order::seller.dashboard.quality', $widgets, true)) {
            $widgets[] = 'order::seller.dashboard.quality';
            config(['seller.dashboard_widgets' => $widgets]);
        }

        View::composer(['seller::seller.dashboard.index', 'order::seller.dashboard.quality'], SellerDashboardComposer::class);
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

        $this->app->bind(SellerOrderDeskRepository::class, function () {
            $repository = new EloquentSellerOrderDeskRepository(new SellerOrder);

            return getModule('order', 'cache') ? new CacheSellerOrderDeskDecorator($repository) : $repository;
        });
    }
}
