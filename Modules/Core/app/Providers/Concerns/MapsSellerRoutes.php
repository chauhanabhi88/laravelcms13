<?php

declare(strict_types=1);

namespace Modules\Core\Providers\Concerns;

use Illuminate\Support\Facades\Route;

/**
 * Supplier panel routes for a module's RouteServiceProvider (M03 §4.1).
 *
 *     use MapsSellerRoutes;
 *
 *     public function map(): void
 *     {
 *         // …api / backend / front…
 *         $this->mapSellerRoutes();
 *     }
 *
 * Loads routes/seller.php under /{seller.panel_prefix} (default "supplier") with the
 * "web", "auth:seller" and "seller.active" middleware, the "seller." name prefix and the
 * `<namespace>\Seller` controller namespace, inside the {locale} prefix when front
 * translation is on. Route names follow seller.<entity>.<action>.
 *
 * "seller.active" sends sellers who have not finished onboarding to the wizard and makes
 * the panel read-only (writes 403) for sellers that are not active, such as suspended ones.
 */
trait MapsSellerRoutes
{
    protected function mapSellerRoutes(string $file = 'routes/seller.php'): void
    {
        $path = $this->sellerRoutesPath($file);

        if (! is_file($path)) {
            return;
        }

        $this->sellerRouteGroup(['web', 'auth:seller', 'seller.active'], function () use ($path): void {
            Route::namespace($this->namespace.'\Seller')->group($path);
        });
    }

    /**
     * Register routes under the supplier panel prefix, locale segment and "seller." names.
     *
     * @param  list<string>  $middleware
     */
    protected function sellerRouteGroup(array $middleware, \Closure $routes): void
    {
        $middleware = config('core.translation_front')
            ? array_merge(['web', 'locale'], array_values(array_diff($middleware, ['web'])))
            : $middleware;

        $group = Route::middleware($middleware)
            ->prefix(trim((string) config('seller.panel_prefix', config('core.seller-prefix', 'supplier')), '/'))
            ->as('seller.');

        if (config('core.translation_front')) {
            Route::prefix('{locale}')->group(fn () => $group->group($routes));

            return;
        }

        $group->group($routes);
    }

    /**
     * Absolute path of a route file of the module that uses the trait.
     */
    protected function sellerRoutesPath(string $file): string
    {
        $reflection = new \ReflectionClass(static::class);

        // <Module>/app/Providers/RouteServiceProvider.php → <Module>/
        return dirname($reflection->getFileName(), 3).'/'.ltrim($file, '/');
    }
}
