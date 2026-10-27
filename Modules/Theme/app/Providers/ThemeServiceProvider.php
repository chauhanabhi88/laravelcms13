<?php

namespace Modules\Theme\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Theme\Composers\FrontendLayoutComposer;
use Modules\Theme\Composers\HeaderComposer;
use Modules\Theme\Composers\SellerLayoutComposer;
use Modules\Theme\Contracts\CartCountProvider;
use Modules\Theme\Contracts\MegaMenuProvider;
use Modules\Theme\Contracts\WishlistProvider;
use Modules\Theme\Models\Theme;
use Modules\Theme\Repositories\Cache\CacheThemeDecorator;
use Modules\Theme\Repositories\Eloquent\EloquentThemeRepository;
use Modules\Theme\Support\MegaMenu;
use Modules\Theme\Support\NullCartCountProvider;
use Modules\Theme\Support\NullMegaMenuProvider;
use Modules\Theme\Support\NullWishlistProvider;
use Modules\Theme\Support\StorefrontLinks;

class ThemeServiceProvider extends ServiceProvider
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
        $this->registerFrontendViews();
        $this->registerBindings();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerStorefrontBindings();
    }

    /**
     * Null providers until Catalog (M02), Customer wishlist (M08) and Cart (M10) bind real ones.
     * "If" bindings so a module that registers earlier still wins.
     */
    protected function registerStorefrontBindings(): void
    {
        $this->app->bindIf(MegaMenuProvider::class, NullMegaMenuProvider::class);
        $this->app->bindIf(CartCountProvider::class, NullCartCountProvider::class);
        $this->app->bindIf(WishlistProvider::class, NullWishlistProvider::class);
        $this->app->bindIf(MegaMenu::class);
        $this->app->scopedIf(StorefrontLinks::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/theme.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'theme'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/theme');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'theme');
    }

    /**
     * Public site: <x-theme::*> anonymous components and the header/footer composer.
     */
    protected function registerFrontendViews(): void
    {
        Blade::anonymousComponentPath(__DIR__.'/../../resources/views/components', 'theme');

        View::composer([
            'theme::layouts.frontend.partials.header',
            'theme::layouts.frontend.partials.footer',
            'theme::layouts.frontend.partials.mobile-drawer',
            'theme::layouts.frontend.partials.bottom-nav',
        ], FrontendLayoutComposer::class);

        View::composer([
            'theme::layouts.frontend.master',
            'theme::layouts.frontend.partials.header',
            'theme::layouts.frontend.partials.mega-menu',
            'theme::layouts.frontend.partials.mobile-drawer',
            'theme::layouts.frontend.partials.bottom-nav',
        ], HeaderComposer::class);

        View::composer([
            'theme::layouts.seller.master',
            'theme::layouts.seller.auth',
        ], SellerLayoutComposer::class);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/theme');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'theme');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'theme');
        }
    }

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Theme\Repositories\ThemeRepository',
            function () {
                $repository = new EloquentThemeRepository(new Theme);

                if (! getModule('theme', 'cache')) {
                    return $repository;
                }

                return new CacheThemeDecorator($repository);
            }
        );
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
