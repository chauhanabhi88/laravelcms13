<?php

declare(strict_types=1);

namespace Modules\Logistics\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Logistics\Console\PollTrackingCommand;
use Modules\Logistics\Drivers\FakeCourierDriver;
use Modules\Logistics\Drivers\ManualCourierDriver;
use Modules\Logistics\Events\NdrRaised;
use Modules\Logistics\Events\ShipmentDelivered;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Logistics\Listeners\CancelShipmentOnSellerOrderCancelled;
use Modules\Logistics\Listeners\HandleRtoDelivered;
use Modules\Logistics\Listeners\MarkCodCollectedOnDelivered;
use Modules\Logistics\Listeners\SendShipmentNotifications;
use Modules\Logistics\Listeners\SyncPickupLocationOnWarehouseSaved;
use Modules\Logistics\Listeners\UpdatePickupBatchOnShipmentStatus;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\PickupBatch;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Repositories\Cache\CacheCourierDecorator;
use Modules\Logistics\Repositories\Cache\CacheCourierRuleDecorator;
use Modules\Logistics\Repositories\Cache\CacheNdrCaseDecorator;
use Modules\Logistics\Repositories\Cache\CachePickupBatchDecorator;
use Modules\Logistics\Repositories\Cache\CacheShipmentDecorator;
use Modules\Logistics\Repositories\CourierRepository;
use Modules\Logistics\Repositories\CourierRuleRepository;
use Modules\Logistics\Repositories\Eloquent\EloquentCourierRepository;
use Modules\Logistics\Repositories\Eloquent\EloquentCourierRuleRepository;
use Modules\Logistics\Repositories\Eloquent\EloquentNdrCaseRepository;
use Modules\Logistics\Repositories\Eloquent\EloquentPickupBatchRepository;
use Modules\Logistics\Repositories\Eloquent\EloquentShipmentRepository;
use Modules\Logistics\Repositories\NdrCaseRepository;
use Modules\Logistics\Repositories\PickupBatchRepository;
use Modules\Logistics\Repositories\ShipmentRepository;
use Modules\Logistics\Support\LogisticsDeliveryEstimator;
use Modules\Logistics\Support\LogisticsFulfilment;
use Modules\Logistics\Support\LogisticsShipmentPlanner;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\Contracts\ShipmentPlanner;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Policies\SellerOwnedPolicy;
use Modules\Storefront\Contracts\DeliveryEstimator;

class LogisticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->registerListeners();
        $this->registerSellerPanel();

        if ($this->app->runningInConsole()) {
            $this->commands([PollTrackingCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // The Order and Storefront modules bind null / matrix defaults with bindIf; logistics
        // replaces them (plan M13 §4).
        $this->app->bind(ShipmentPlanner::class, LogisticsShipmentPlanner::class);
        $this->app->bind(DeliveryEstimator::class, LogisticsDeliveryEstimator::class);
        $this->app->bind(FulfilmentLogistics::class, LogisticsFulfilment::class);

        // The fake driver keeps its queued scans and calls for the whole request / test.
        $this->app->singleton(FakeCourierDriver::class);
        $this->app->singleton(ManualCourierDriver::class);
    }

    protected function registerListeners(): void
    {
        Event::listen(ShipmentStatusChanged::class, [SendShipmentNotifications::class, 'handleStatusChanged']);
        Event::listen(NdrRaised::class, [SendShipmentNotifications::class, 'handleNdr']);
        Event::listen(ShipmentDelivered::class, MarkCodCollectedOnDelivered::class);
        Event::listen(ShipmentRtoDelivered::class, MarkCodCollectedOnDelivered::class);
        Event::listen(ShipmentRtoDelivered::class, HandleRtoDelivered::class);
        Event::listen(SellerOrderStatusChanged::class, CancelShipmentOnSellerOrderCancelled::class);
        Event::listen(ShipmentStatusChanged::class, UpdatePickupBatchOnShipmentStatus::class);

        Event::listen(SellerApproved::class, [SyncPickupLocationOnWarehouseSaved::class, 'sellerApproved']);
        Event::listen(SellerReactivated::class, [SyncPickupLocationOnWarehouseSaved::class, 'sellerApproved']);
        SellerWarehouse::saved(fn (SellerWarehouse $warehouse) => $this->app->make(SyncPickupLocationOnWarehouseSaved::class)->saved($warehouse));
    }

    /**
     * Supplier panel: pickup batches are seller-owned (SellerOwnedPolicy answers another
     * seller's batch with 404) and "Manifests" joins the Order module's "Orders" sidebar
     * section (plan M14 §6).
     */
    protected function registerSellerPanel(): void
    {
        Gate::policy(PickupBatch::class, SellerOwnedPolicy::class);

        $menu = (array) config('theme.seller_menu', []);
        $menu['orders']['label'] ??= 'order::fulfilment.menu.orders';
        $items = array_filter((array) ($menu['orders']['items'] ?? []), fn (array $item): bool => ($item['route'] ?? null) !== 'seller.manifest.index');
        $items[] = ['route' => 'seller.manifest.index', 'label' => 'logistics::pickup_batch.seller.menu', 'icon' => 'document', 'active' => 'seller.manifest.*'];
        $menu['orders']['items'] = array_values($items);

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/logistics.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'logistics');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/logistics'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'logistics');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/logistics');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'logistics');
    }

    /**
     * Admin grid repositories, wrapped in the (pass-through) cache decorators when the
     * module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(CourierRepository::class, function () {
            $repository = new EloquentCourierRepository(new Courier);

            return getModule('logistics', 'cache') ? new CacheCourierDecorator($repository) : $repository;
        });

        $this->app->bind(CourierRuleRepository::class, function () {
            $repository = new EloquentCourierRuleRepository(new CourierRule);

            return getModule('logistics', 'cache') ? new CacheCourierRuleDecorator($repository) : $repository;
        });

        $this->app->bind(ShipmentRepository::class, function () {
            $repository = new EloquentShipmentRepository(new Shipment);

            return getModule('logistics', 'cache') ? new CacheShipmentDecorator($repository) : $repository;
        });

        $this->app->bind(NdrCaseRepository::class, function () {
            $repository = new EloquentNdrCaseRepository(new NdrCase);

            return getModule('logistics', 'cache') ? new CacheNdrCaseDecorator($repository) : $repository;
        });

        $this->app->bind(PickupBatchRepository::class, function () {
            $repository = new EloquentPickupBatchRepository(new PickupBatch);

            return getModule('logistics', 'cache') ? new CachePickupBatchDecorator($repository) : $repository;
        });
    }
}
