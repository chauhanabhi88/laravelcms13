<?php

declare(strict_types=1);

namespace Modules\Product\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Product\Composers\LowStockCardComposer;
use Modules\Product\Console\LowStockDigestCommand;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductBlocked;
use Modules\Product\Events\ProductRejected;
use Modules\Product\Events\ProductSubmitted;
use Modules\Product\Events\VariantLowStock;
use Modules\Product\Listeners\FlagLowStockVariant;
use Modules\Product\Listeners\FlushProductCaches;
use Modules\Product\Listeners\PauseSellerProducts;
use Modules\Product\Listeners\ResumeSellerVisibility;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Repositories\Cache\CacheInventoryMovementDecorator;
use Modules\Product\Repositories\Cache\CacheProductDecorator;
use Modules\Product\Repositories\Cache\CacheSellerCatalogDecorator;
use Modules\Product\Repositories\Eloquent\EloquentInventoryMovementRepository;
use Modules\Product\Repositories\Eloquent\EloquentProductRepository;
use Modules\Product\Repositories\Eloquent\EloquentSellerCatalogRepository;
use Modules\Product\Repositories\InventoryMovementRepository;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Repositories\SellerCatalogRepository;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Support\CategoryInUseChecker;
use Modules\Product\Support\Import\ImageFetcher;
use Modules\Product\Support\ZeroFeeCalculator;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Policies\SellerOwnedPolicy;

class ProductServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Supplier panel tenancy (M05): a seller manages only their own products and variants.
        Gate::policy(Product::class, SellerOwnedPolicy::class);
        Gate::policy(ProductVariant::class, SellerOwnedPolicy::class);
        Gate::policy(CatalogUpload::class, SellerOwnedPolicy::class);

        Event::listen(SellerSuspended::class, PauseSellerProducts::class);
        Event::listen(SellerReactivated::class, ResumeSellerVisibility::class);
        Event::listen([
            ProductSubmitted::class,
            ProductApproved::class,
            ProductRejected::class,
            ProductBlocked::class,
            ProductAggregatesChanged::class,
        ], FlushProductCaches::class);
        Event::listen(VariantLowStock::class, FlagLowStockVariant::class);

        if ($this->app->runningInConsole()) {
            $this->commands([LowStockDigestCommand::class]);
        }

        $this->registerSellerPanel();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // Catalog binds a null checker with bindIf; this binding wins whichever module registers first.
        $this->app->bind(CategoryInUse::class, CategoryInUseChecker::class);

        // The Finance module (M17) rebinds this to its commission engine.
        $this->app->bindIf(FeeCalculator::class, ZeroFeeCalculator::class);

        // Bulk image URLs resolve hosts through DNS; tests swap in a fixed resolver.
        $this->app->bind(ImageFetcher::class, fn ($app): ImageFetcher => new ImageFetcher($app->make(ProductImageService::class)));
    }

    /**
     * Supplier panel: the "Catalog" sidebar section (before Settings) and the low-stock
     * dashboard card (rendered by the Seller dashboard from config seller.dashboard_widgets).
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        unset($menu['catalog']);

        config(['theme.seller_menu' => ['catalog' => [
            'label' => 'product::seller.menu.catalog',
            'items' => [
                ['route' => 'seller.listing.create', 'label' => 'product::seller.menu.add', 'icon' => 'plus', 'active' => 'seller.listing.*'],
                ['route' => 'seller.upload.create', 'label' => 'product::seller.menu.bulk_upload', 'icon' => 'upload', 'active' => 'seller.upload.create'],
                ['route' => 'seller.upload.index', 'label' => 'product::seller.menu.upload_history', 'icon' => 'clock', 'active' => ['seller.upload.index', 'seller.upload.show']],
                ['route' => 'seller.product.index', 'label' => 'product::seller.menu.products', 'icon' => 'package', 'active' => 'seller.product.*'],
                ['route' => 'seller.inventory.index', 'label' => 'product::seller.menu.inventory', 'icon' => 'grid', 'active' => 'seller.inventory.*'],
                ['route' => 'seller.price_stock.create', 'label' => 'product::seller.menu.price_stock', 'icon' => 'tag', 'active' => 'seller.price_stock.*'],
                ['route' => 'seller.calculator', 'label' => 'product::seller.menu.calculator', 'icon' => 'wallet', 'active' => 'seller.calculator*'],
            ],
        ]] + $menu]);

        $widgets = (array) config('seller.dashboard_widgets', []);
        if (! in_array('product::seller.dashboard.low-stock', $widgets, true)) {
            $widgets[] = 'product::seller.dashboard.low-stock';
            config(['seller.dashboard_widgets' => $widgets]);
        }

        View::composer('product::seller.dashboard.low-stock', LowStockCardComposer::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/product.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'product');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/product'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'product');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/product');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'product');
    }

    /**
     * Repositories bound to Eloquent, wrapped in the cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $repositories = [
            ProductRepository::class => [EloquentProductRepository::class, Product::class, CacheProductDecorator::class],
            InventoryMovementRepository::class => [EloquentInventoryMovementRepository::class, InventoryMovement::class, CacheInventoryMovementDecorator::class],
            SellerCatalogRepository::class => [EloquentSellerCatalogRepository::class, Product::class, CacheSellerCatalogDecorator::class],
        ];

        foreach ($repositories as $contract => [$eloquent, $model, $decorator]) {
            $this->app->bind($contract, function () use ($eloquent, $model, $decorator) {
                $repository = new $eloquent(new $model);

                if (! getModule('product', 'cache')) {
                    return $repository;
                }

                return new $decorator($repository);
            });
        }
    }
}
