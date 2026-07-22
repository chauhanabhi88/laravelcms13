<?php

namespace Modules\Column\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Column\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     */
    public function map(): void
    {
        if (config('core.translation_api')) {
            Route::prefix('{locale}')
                ->middleware(['locale'])
                ->group(function () {
                    $this->mapApiRoutes();
                });
        } else {
            $this->mapApiRoutes();
        }
        
        if (config('core.translation')) {
            $route = Route::prefix('{locale}')
                ->middleware(['web', 'locale']);
        } else {
            $route = Route::middleware(['web']);
        }
        $route->group(function () {
            $this->mapBackendRoutes();
        });
    }

    /**
     * Define the "backend" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     */
    protected function mapBackendRoutes(): void
    {
        $adminPrefix = \Config::get("core.admin-prefix");
        Route::prefix($adminPrefix)
            ->middleware('web')
            ->namespace($this->namespace."\Backend")
            ->group(__DIR__ . '/../../routes/backend.php');
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace . "\Api")
            ->group(__DIR__ . '/../../routes/api.php');
    }
}
