<?php

declare(strict_types=1);

namespace Modules\Product\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\Core\Providers\Concerns\MapsSellerRoutes;

/**
 * Admin product screens (backend.php): all products, the QC queue and the inventory ledger.
 * Supplier panel catalog tools (seller.php via MapsSellerRoutes, M05): products, listing
 * wizard, bulk uploads, inventory and the earnings calculator. Storefront pages belong to
 * the Storefront module (M06/M07).
 */
class RouteServiceProvider extends ServiceProvider
{
    use MapsSellerRoutes;

    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\Product\Http\Controllers';

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

        $this->mapSellerRoutes();
    }

    protected function mapBackendRoutes(): void
    {
        Route::prefix(Config::get('core.admin-prefix'))
            ->middleware(['backend'])
            ->namespace($this->namespace.'\Backend')
            ->group(__DIR__.'/../../routes/backend.php');
    }
}
