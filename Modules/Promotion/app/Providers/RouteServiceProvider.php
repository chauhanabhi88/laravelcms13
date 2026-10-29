<?php

declare(strict_types=1);

namespace Modules\Promotion\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Promotion routes:
 * - api.php      api/{version}/cart/coupon (POST/DELETE), api/{version}/cart/offers
 * - backend.php  coupons, flash deals, promotion report (/backend/...)
 * - front.php    /cart/coupon, /cart/offers, /r/{code}, /customer/referral
 * - seller.php   supplier coupons (/supplier/coupons)
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Promotion\Http\Controllers';

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
