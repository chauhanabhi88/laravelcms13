<?php

namespace Modules\Contact\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Contact\Http\Controllers';

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


        if (config('core.translation_front')) {
            $frontRoute = Route::prefix('{locale}')
                ->middleware(['web', 'locale']);
        } else {
            $frontRoute = Route::middleware(['web']);
        }
        $frontRoute->group(function () {
            $this->mapFrontRoutes();
        });
    }
 
    /**
     * Define the "backend" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapBackendRoutes()
    {
        $adminPrefix = \Config::get("core.admin-prefix");
        Route::prefix($adminPrefix)
            ->middleware(["backend"])
            ->namespace($this->namespace."\Backend")
            ->group(__DIR__ . '/../../routes/backend.php');
    }   
    
    protected function mapFrontRoutes()
    {
        Route::namespace($this->namespace)
            ->group(__DIR__ . '/../../routes/front.php');
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
