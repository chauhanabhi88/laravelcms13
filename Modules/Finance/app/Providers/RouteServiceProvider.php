<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Finance routes:
 * - backend.php  the admin finance console (/backend/finance/...)
 * - front.php    the reseller's earnings page (/customer/earnings)
 * - api.php      the same for the mobile apps (/api/v1/customer/earnings)
 * - seller.php   supplier payments: overview, statement, payouts, invoices (/supplier/payments...)
 * RazorpayX payout webhooks arrive on the Seller module's /webhooks/razorpayx.
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Finance\Http\Controllers';

    public function map(): void
    {
        if (config('core.translation_api')) {
            Route::prefix('{locale}')->middleware(['locale'])->group(fn () => $this->mapApiRoutes());
        } else {
            $this->mapApiRoutes();
        }

        if (config('core.translation')) {
            $route = Route::prefix('{locale}')->middleware(['web', 'locale']);
        } else {
            $route = Route::middleware(['web']);
        }

        $route->group(function (): void {
            Route::prefix(Config::get('core.admin-prefix'))
                ->middleware(['backend'])
                ->namespace($this->namespace.'\Backend')
                ->group(__DIR__.'/../../routes/backend.php');
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
}
