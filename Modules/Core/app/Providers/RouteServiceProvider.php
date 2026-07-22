<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Core\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        if (config('core.translation')) {
            $route = Route::prefix('{locale}')
                ->middleware(['web', 'locale']);
        } else {
            $route = Route::middleware(['web']);
        }
        $route->group(function () {
            $this->mapBackendRoutes();
        });

        Route::middleware(['web'])
            ->group(function () {
                $this->mapFrontRoutes();
            });
    }

    /**
     * Define the "front" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapFrontRoutes()
    {
        Route::namespace($this->namespace)
            ->group(__DIR__ . '/../../routes/front.php');
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapBackendRoutes()
    {
        $adminPrefix = config("core.admin-prefix");
        Route::prefix($adminPrefix)
            ->namespace($this->namespace . "\Backend")
            ->group(__DIR__ . '/../../routes/backend.php');
    }
}
