<?php

declare(strict_types=1);

namespace Modules\Seller\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Seller routes:
 * - backend.php  admin KYC queue and seller management (/backend)
 * - front.php    public "Sell with Us" landing (/supplier); the store page is Storefront's (M06)
 * - account.php  supplier sign-in, registration, password reset and the onboarding wizard:
 *                /supplier, "web" only (the wizard adds "auth:seller" itself)
 * - seller.php   the supplier panel (MapsSellerRoutes: auth:seller + seller.active)
 * - webhook.php  provider callbacks (/webhooks/..., signature-verified, no session)
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Seller\Http\Controllers';

    public function map(): void
    {
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

        // Account routes before the public ones: /supplier/login must not hit a catch-all.
        $this->sellerRouteGroup(['web'], function (): void {
            Route::namespace($this->namespace.'\Seller')->group(__DIR__.'/../../routes/account.php');
        });

        $this->mapSellerRoutes();

        if (config('core.translation_front')) {
            $frontRoute = Route::prefix('{locale}')->middleware(['web', 'locale']);
        } else {
            $frontRoute = Route::middleware(['web']);
        }

        $frontRoute->group(function (): void {
            Route::namespace($this->namespace)->group(__DIR__.'/../../routes/front.php');
        });
    }

    protected function mapBackendRoutes(): void
    {
        Route::prefix(Config::get('core.admin-prefix'))
            ->middleware(['backend'])
            ->namespace($this->namespace.'\Backend')
            ->group(__DIR__.'/../../routes/backend.php');
    }
}
