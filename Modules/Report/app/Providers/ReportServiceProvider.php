<?php

declare(strict_types=1);

namespace Modules\Report\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Dashboard\Contracts\DashboardWidget;
use Modules\Report\Console\AggregateNightlyCommand;
use Modules\Report\Console\AggregateTodayCommand;
use Modules\Report\Console\RebuildStatsCommand;
use Modules\Report\Support\ReportDashboardWidget;

class ReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RebuildStatsCommand::class,
                AggregateNightlyCommand::class,
                AggregateTodayCommand::class,
            ]);
        }

        $this->registerSellerPanel();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Marketplace KPIs on the backend dashboard home (replaces the CMS-only overview there).
        $this->app->tag([ReportDashboardWidget::class], DashboardWidget::TAG);
    }

    /**
     * Supplier panel: an "Insights" sidebar section with the seller's analytics (FR-RPT-006).
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['insights'] = [
            'label' => 'report::seller.menu.section',
            'items' => [
                ['route' => 'seller.analytics.index', 'label' => 'report::seller.menu.analytics', 'icon' => 'trending-up', 'active' => 'seller.analytics*'],
            ],
        ];

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/report.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'report');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/report'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'report');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/report');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'report');
    }
}
