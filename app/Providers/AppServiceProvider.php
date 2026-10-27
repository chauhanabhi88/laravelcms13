<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\StrictLoading;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Legacy lazy loads already reported, so each one is logged once per process.
     *
     * @var array<string, true>
     */
    private static array $reportedLazyLoads = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->preventLazyLoading();
    }

    /**
     * Outside production a lazy load throws for the marketplace models (StrictLoading)
     * and is only logged for the legacy modules, so N+1s surface without breaking old screens.
     */
    private function preventLazyLoading(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if ($model instanceof StrictLoading) {
                throw new LazyLoadingViolationException($model, $relation);
            }

            $key = $model::class.'::'.$relation;

            if (! isset(self::$reportedLazyLoads[$key])) {
                self::$reportedLazyLoads[$key] = true;
                Log::warning(sprintf('Lazy loading [%s] on [%s]; eager load it to avoid an N+1 query.', $relation, $model::class));
            }
        });
    }
}
