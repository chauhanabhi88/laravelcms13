<?php

namespace Modules\LaravelPWA\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\LaravelPWA\Http\Controllers';

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
     * Define the "web" routes for the application.
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
}