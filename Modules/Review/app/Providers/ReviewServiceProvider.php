<?php

declare(strict_types=1);

namespace Modules\Review\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Customer\Events\CustomerAnonymised;
use Modules\Order\Contracts\OrderReviews;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Review\Events\ReviewStatusChanged;
use Modules\Review\Listeners\AnonymiseReviewAuthor;
use Modules\Review\Listeners\PromptReviewAfterDelivery;
use Modules\Review\Listeners\RecalculateAggregatesOnPublish;
use Modules\Review\Models\ProductReview;
use Modules\Review\Repositories\Cache\CacheProductReviewDecorator;
use Modules\Review\Repositories\Eloquent\EloquentProductReviewRepository;
use Modules\Review\Repositories\ProductReviewRepository;
use Modules\Review\Support\ProductReviewSummaryProvider;
use Modules\Review\Support\ReviewsForOrders;
use Modules\Seller\Policies\SellerOwnedPolicy;
use Modules\Storefront\Contracts\ReviewSummaryProvider;

class ReviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Supplier panel tenancy: a review of another seller's product answers 404.
        Gate::policy(ProductReview::class, SellerOwnedPolicy::class);

        Event::listen(ReviewStatusChanged::class, RecalculateAggregatesOnPublish::class);
        Event::listen(SellerOrderStatusChanged::class, PromptReviewAfterDelivery::class);
        Event::listen(CustomerAnonymised::class, AnonymiseReviewAuthor::class);

        $this->registerSellerPanel();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // The Storefront (M07) and Order (M12) modules bind null providers with bindIf.
        $this->app->bind(ReviewSummaryProvider::class, ProductReviewSummaryProvider::class);
        $this->app->bind(OrderReviews::class, ReviewsForOrders::class);
    }

    /**
     * Supplier panel: "Reviews" joins the Product module's "Catalog" sidebar section.
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['catalog']['label'] ??= 'product::seller.menu.catalog';
        $items = array_filter((array) ($menu['catalog']['items'] ?? []), fn (array $item): bool => ($item['route'] ?? null) !== 'seller.review.index');
        $items[] = ['route' => 'seller.review.index', 'label' => 'review::seller.menu', 'icon' => 'star', 'active' => 'seller.review*'];
        $menu['catalog']['items'] = array_values($items);

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/review.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'review');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/review'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'review');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/review');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'review');
    }

    /**
     * Admin grid repository, wrapped in the (pass-through) cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(ProductReviewRepository::class, function () {
            $repository = new EloquentProductReviewRepository(new ProductReview);

            return getModule('review', 'cache') ? new CacheProductReviewDecorator($repository) : $repository;
        });
    }
}
