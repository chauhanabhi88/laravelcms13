<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Notification routes:
 * - api.php      api/{version}/customer/notifications, /notifications/{id}/read, /device-tokens
 * - backend.php  templates (CRUD + test send) and the delivery log (/backend)
 * - front.php    customer inbox + preferences under /customer/notifications
 * - seller.php   seller inbox + preferences under /supplier
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Notification\Http\Controllers';

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

        $this->mapSellerInboxRoutes();
    }

    /**
     * Supplier panel routes with "seller.onboarded" instead of "seller.active": a suspended
     * seller must still be able to read (and mark read) the notices about the suspension.
     */
    protected function mapSellerInboxRoutes(): void
    {
        $path = $this->sellerRoutesPath('routes/seller.php');

        $this->sellerRouteGroup(['web', 'auth:seller', 'seller.onboarded'], function () use ($path): void {
            Route::namespace($this->namespace.'\Seller')->group($path);
        });
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
