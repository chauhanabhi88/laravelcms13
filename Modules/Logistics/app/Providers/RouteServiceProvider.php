<?php

declare(strict_types=1);

namespace Modules\Logistics\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Logistics routes:
 * - api.php      api/{version}/orders/{orderNo}/tracking
 * - backend.php  couriers, rules + simulator, shipments, NDR, pickup batches (/backend/logistics/...)
 * - front.php    /track and /track/{awbOrOrderNo} (public, throttled)
 * - webhook.php  /webhooks/shiprocket (token-verified, no session or CSRF)
 * - seller.php   supplier manifests / pickup batches (/supplier/orders/manifests..., M14)
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Logistics\Http\Controllers';

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

        Route::prefix('webhooks')
            ->middleware('webhook')
            ->namespace($this->namespace.'\Webhook')
            ->group(__DIR__.'/../../routes/webhook.php');

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
