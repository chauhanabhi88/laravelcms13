<?php

declare(strict_types=1);

namespace Modules\Promotion\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Cart\Services\CartTotals;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Order\Contracts\OrderPromotions;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Product\Contracts\PriceResolver;
use Modules\Promotion\Adjustments\AutomaticOfferAdjustment;
use Modules\Promotion\Adjustments\CouponAdjustment;
use Modules\Promotion\Listeners\AttachReferralOnRegister;
use Modules\Promotion\Listeners\QualifyReferralOnDelivered;
use Modules\Promotion\Listeners\ReverseCouponUsageOnCancel;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Repositories\Cache\CachePromotionGridDecorator;
use Modules\Promotion\Repositories\Eloquent\EloquentPromotionGridRepository;
use Modules\Promotion\Repositories\PromotionGridRepository;
use Modules\Promotion\Services\FlashDealPricing;
use Modules\Promotion\Services\PromotionOrderHooks;
use Modules\Promotion\Support\FlashDealHomeSection;
use Modules\Seller\Policies\SellerOwnedPolicy;
use Modules\Storefront\Services\Home\HomePageBuilder;

class PromotionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Supplier panel tenancy: another seller's coupon answers 404.
        Gate::policy(Coupon::class, SellerOwnedPolicy::class);

        Event::listen(OrderCancelled::class, ReverseCouponUsageOnCancel::class);
        Event::listen(CustomerRegistered::class, AttachReferralOnRegister::class);
        Event::listen(SellerOrderStatusChanged::class, QualifyReferralOnDelivered::class);

        // Home page section type with the running flash deal and its countdown.
        $types = (array) config('storefront.home_section_types', []);
        if (! in_array(FlashDealHomeSection::TYPE, $types, true)) {
            config(['storefront.home_section_types' => array_merge($types, [FlashDealHomeSection::TYPE])]);
        }

        $this->registerSellerPanel();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // The Product and Order modules bind no-op implementations with bindIf.
        $this->app->bind(PriceResolver::class, FlashDealPricing::class);
        $this->app->bind(OrderPromotions::class, PromotionOrderHooks::class);

        // Price-summary pipeline (after the Cart's delivery and COD fee steps): the code coupon,
        // then the automatic offer.
        $this->app->tag([CouponAdjustment::class, AutomaticOfferAdjustment::class], CartTotals::ADJUSTMENT_TAG);
        $this->app->tag([FlashDealHomeSection::class], HomePageBuilder::SECTION_PROVIDER_TAG);
    }

    /**
     * Supplier panel: a "Promotions" sidebar section with the seller's own coupons.
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['promotions'] = [
            'label' => 'promotion::seller.menu.section',
            'items' => [
                ['route' => 'seller.coupon.index', 'label' => 'promotion::seller.menu.coupons', 'icon' => 'tag', 'active' => 'seller.coupon*'],
            ],
        ];

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/promotion.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'promotion');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/promotion'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'promotion');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/promotion');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'promotion');
    }

    /**
     * Admin grid repository (coupons, flash deals), wrapped in the (pass-through) cache decorator
     * when the module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(PromotionGridRepository::class, function ($app, array $parameters) {
            $grid = (string) ($parameters['grid'] ?? 'coupon');
            $repository = new EloquentPromotionGridRepository($grid === 'flash_deal' ? new FlashDeal : new Coupon, $grid);

            return getModule('promotion', 'cache') ? new CachePromotionGridDecorator($repository) : $repository;
        });
    }
}
