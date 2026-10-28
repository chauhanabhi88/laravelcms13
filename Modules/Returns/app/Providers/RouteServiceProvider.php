<?php

declare(strict_types=1);

namespace Modules\Returns\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Returns routes:
 * - api.php      api/{version}/orders/{orderNo}/returnable-items, api/{version}/returns[/{returnNo}[/cancel]]
 * - backend.php  returns console and claims (/backend/return-request, /backend/return-claim)
 * - front.php    /customer/orders/{orderNo}/return, /customer/returns...
 * - seller.php   supplier returns and claims (/supplier/returns...)
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Returns\Http\Controllers';

    public function map(): void
    {
        if (config('core.translation_api')) {
            Route::prefix('{locale}')
                ->middleware(['locale'])
                ->group(function (): void {
                    $this->mapApiRoutes();
                });
        } else {
            $this->mapApiRoutes();
        }

        if (config('core.translation')) {
            $route = Route::prefix('{locale}')->middleware(['web', 'locale']);
        } else {
            $route = Route::middleware(['web']);
        }

        $route->group(function (): void {
            $this->mapBackendRoutes();
        });

        if (config('core.translation_front')) {
            $frontRoute = Route::prefix('{locale}')->middleware(['web', 'locale']);
        } else {
            $frontRoute = Route::middleware(['web']);
        }

        $frontRoute->group(function (): void {
            Route::namespace($this->namespace)->group(__DIR__.'/../../routes/front.php');
        });

        $this->mapSellerRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace.'\Api')
            ->group(__DIR__.'/../../routes/api.php');
    }

    protected function mapBackendRoutes(): void
    {
        Route::prefix(Config::get('core.admin-prefix'))
            ->middleware(['backend'])
            ->namespace($this->namespace.'\Backend')
            ->group(__DIR__.'/../../routes/backend.php');
    }
}
