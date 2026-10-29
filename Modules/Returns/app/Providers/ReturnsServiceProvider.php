<?php

declare(strict_types=1);

namespace Modules\Returns\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ProvidesAppConfig;
use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Order\Contracts\OrderReturns;
use Modules\Order\Events\ReturnReceiptConfirmed;
use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Events\RefundProcessed;
use Modules\Returns\Listeners\AdvanceReturnOnReverseShipmentStatus;
use Modules\Returns\Listeners\OpenClaimFromRtoReceipt;
use Modules\Returns\Listeners\RefundPrepaidRto;
use Modules\Returns\Listeners\UpdateReturnOnRefund;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Repositories\Cache\CacheReturnClaimDecorator;
use Modules\Returns\Repositories\Cache\CacheReturnRequestDecorator;
use Modules\Returns\Repositories\Eloquent\EloquentReturnClaimRepository;
use Modules\Returns\Repositories\Eloquent\EloquentReturnRequestRepository;
use Modules\Returns\Repositories\ReturnClaimRepository;
use Modules\Returns\Repositories\ReturnRequestRepository;
use Modules\Returns\Support\ReturnsAccountDeletionGuard;
use Modules\Returns\Support\ReturnsAppConfig;
use Modules\Returns\Support\ReturnsForOrders;
use Modules\Seller\Policies\SellerOwnedPolicy;

class ReturnsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Supplier panel tenancy: another seller's return or claim answers 404.
        Gate::policy(ReturnRequest::class, SellerOwnedPolicy::class);
        Gate::policy(ReturnClaim::class, SellerOwnedPolicy::class);

        $this->registerListeners();
        $this->registerSellerPanel();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        $this->app->tag([ReturnsAppConfig::class], ProvidesAppConfig::TAG);
        // The Order module binds a null OrderReturns with bindIf; returns replace it.
        $this->app->bind(OrderReturns::class, ReturnsForOrders::class);

        // Open returns hold an account deletion, after the Order module's own checks.
        $this->app->extend(AccountDeletionGuard::class, fn (AccountDeletionGuard $guard): AccountDeletionGuard => new ReturnsAccountDeletionGuard($guard));
    }

    protected function registerListeners(): void
    {
        Event::listen(ShipmentStatusChanged::class, AdvanceReturnOnReverseShipmentStatus::class);
        Event::listen(ShipmentRtoDelivered::class, RefundPrepaidRto::class);
        Event::listen(RefundProcessed::class, [UpdateReturnOnRefund::class, 'processed']);
        Event::listen(RefundFailed::class, [UpdateReturnOnRefund::class, 'failed']);
        Event::listen(ReturnReceiptConfirmed::class, OpenClaimFromRtoReceipt::class);
    }

    /**
     * Supplier panel: "Returns" joins the Order module's "Orders" sidebar section.
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['orders']['label'] ??= 'order::fulfilment.menu.orders';
        $items = array_filter((array) ($menu['orders']['items'] ?? []), fn (array $item): bool => ($item['route'] ?? null) !== 'seller.return.index');
        $items[] = ['route' => 'seller.return.index', 'label' => 'returns::seller.menu', 'icon' => 'rotate-ccw', 'active' => 'seller.return*'];
        $menu['orders']['items'] = array_values($items);

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/returns.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'returns');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/returns'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'returns');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/returns');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'returns');
    }

    /**
     * Admin grid repositories, wrapped in the (pass-through) cache decorators when the
     * module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(ReturnRequestRepository::class, function () {
            $repository = new EloquentReturnRequestRepository(new ReturnRequest);

            return getModule('returns', 'cache') ? new CacheReturnRequestDecorator($repository) : $repository;
        });

        $this->app->bind(ReturnClaimRepository::class, function () {
            $repository = new EloquentReturnClaimRepository(new ReturnClaim);

            return getModule('returns', 'cache') ? new CacheReturnClaimDecorator($repository) : $repository;
        });
    }
}
