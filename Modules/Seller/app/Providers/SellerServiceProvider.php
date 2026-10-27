<?php

declare(strict_types=1);

namespace Modules\Seller\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Seller\Contracts\BankVerifier;
use Modules\Seller\Contracts\GstinVerifier;
use Modules\Seller\Drivers\Bank\FakeBankVerifier;
use Modules\Seller\Drivers\Bank\ManualBankVerifier;
use Modules\Seller\Drivers\Bank\RazorpayXBankVerifier;
use Modules\Seller\Drivers\Gst\HttpGstinVerifier;
use Modules\Seller\Drivers\Gst\NullGstinVerifier;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerRejected;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Http\Middleware\EnsureSellerActive;
use Modules\Seller\Http\Middleware\EnsureSellerOnboarded;
use Modules\Seller\Http\Middleware\SellerCan;
use Modules\Seller\Listeners\NotifySellerOfDecision;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Policies\SellerOwnedPolicy;
use Modules\Seller\Repositories\Cache\CacheSellerBankAccountDecorator;
use Modules\Seller\Repositories\Cache\CacheSellerDecorator;
use Modules\Seller\Repositories\Cache\CacheSellerUserDecorator;
use Modules\Seller\Repositories\Cache\CacheSellerWarehouseDecorator;
use Modules\Seller\Repositories\Eloquent\EloquentSellerBankAccountRepository;
use Modules\Seller\Repositories\Eloquent\EloquentSellerRepository;
use Modules\Seller\Repositories\Eloquent\EloquentSellerUserRepository;
use Modules\Seller\Repositories\Eloquent\EloquentSellerWarehouseRepository;
use Modules\Seller\Repositories\SellerBankAccountRepository;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Seller\Repositories\SellerUserRepository;
use Modules\Seller\Repositories\SellerWarehouseRepository;
use Modules\Seller\Support\CurrentSeller;
use Modules\Seller\Support\SellerSettings;

class SellerServiceProvider extends ServiceProvider
{
    /**
     * Seller-owned models guarded by SellerOwnedPolicy in the supplier panel.
     *
     * @var list<class-string>
     */
    protected array $sellerOwnedModels = [
        SellerWarehouse::class,
        SellerBankAccount::class,
        SellerKycDocument::class,
        SellerUser::class,
    ];

    public function boot(Router $router): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $router->aliasMiddleware('seller.active', EnsureSellerActive::class);
        $router->aliasMiddleware('seller.onboarded', EnsureSellerOnboarded::class);
        $router->aliasMiddleware('seller.can', SellerCan::class);

        foreach ($this->sellerOwnedModels as $model) {
            Gate::policy($model, SellerOwnedPolicy::class);
        }

        Event::listen([
            SellerApproved::class,
            SellerRejected::class,
            SellerChangesRequested::class,
            SellerSuspended::class,
            SellerReactivated::class,
        ], NotifySellerOfDecision::class);

        $this->registerSellerMenu();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        // Memoises per Request itself, so a plain binding is enough.
        $this->app->bind(CurrentSeller::class);

        // One fake instance per app, so tests can script its answers.
        $this->app->singleton(FakeBankVerifier::class);

        $this->app->bind(BankVerifier::class, function (Application $app): BankVerifier {
            $driver = SellerSettings::bankVerifier();

            return match ($driver) {
                'razorpayx' => new RazorpayXBankVerifier((array) $app['config']->get('seller.razorpayx', [])),
                'manual' => new ManualBankVerifier,
                'fake' => $app->make(FakeBankVerifier::class),
                default => throw new InvalidArgumentException("Unsupported bank verifier [{$driver}]."),
            };
        });

        $this->app->bind(GstinVerifier::class, fn (): GstinVerifier => SellerSettings::gstVerifier() === 'http'
            ? new HttpGstinVerifier(SellerSettings::gstVerifierUrl(), SellerSettings::gstVerifierKey())
            : new NullGstinVerifier);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/seller.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'seller');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/seller'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'seller');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/seller');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'seller');
    }

    /**
     * Supplier panel sidebar (config theme.seller_menu, rendered by Theme's SellerLayoutComposer).
     * Later modules append their own sections (catalog, orders, payments).
     */
    protected function registerSellerMenu(): void
    {
        $menu = (array) config('theme.seller_menu', []);

        $menu['settings'] = [
            'label' => 'seller::panel.menu.settings',
            'items' => [
                ['route' => 'seller.profile.edit', 'label' => 'seller::panel.menu.business', 'icon' => 'store', 'active' => 'seller.profile.*'],
                ['route' => 'seller.bank.index', 'label' => 'seller::panel.menu.bank', 'icon' => 'banknote', 'active' => 'seller.bank.*'],
                ['route' => 'seller.warehouse.index', 'label' => 'seller::panel.menu.warehouses', 'icon' => 'map-pin', 'active' => 'seller.warehouse.*'],
                ['route' => 'seller.staff.index', 'label' => 'seller::panel.menu.staff', 'icon' => 'user', 'active' => 'seller.staff.*'],
            ],
        ];

        config(['theme.seller_menu' => $menu]);
    }

    /**
     * Repositories bound to Eloquent, wrapped in the cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $repositories = [
            SellerRepository::class => [EloquentSellerRepository::class, Seller::class, CacheSellerDecorator::class],
            SellerWarehouseRepository::class => [EloquentSellerWarehouseRepository::class, SellerWarehouse::class, CacheSellerWarehouseDecorator::class],
            SellerUserRepository::class => [EloquentSellerUserRepository::class, SellerUser::class, CacheSellerUserDecorator::class],
            SellerBankAccountRepository::class => [EloquentSellerBankAccountRepository::class, SellerBankAccount::class, CacheSellerBankAccountDecorator::class],
        ];

        foreach ($repositories as $contract => [$eloquent, $model, $decorator]) {
            $this->app->bind($contract, function () use ($eloquent, $model, $decorator) {
                $repository = new $eloquent(new $model);

                if (! getModule('seller', 'cache')) {
                    return $repository;
                }

                return new $decorator($repository);
            });
        }
    }
}
