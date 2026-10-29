<?php

namespace Modules\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Core\Cache\FileStore;
use Modules\Core\Commands\Database\SeedCommand;
use Modules\Core\Commands\Make\MigrationMakeCommand;
use Modules\Core\Commands\Make\ModuleMakeCommand;
use Modules\Core\Console\CronMakeCommand;
use Modules\Core\Console\CustomBladeCreateCommand;
use Modules\Core\Console\CustomBladeEditCommand;
use Modules\Core\Console\CustomBladeGridCommand;
use Modules\Core\Console\CustomBladeIndexCommand;
use Modules\Core\Console\CustomCacheCommand;
use Modules\Core\Console\CustomControllerCommand;
use Modules\Core\Console\CustomCreateRequestCommand;
use Modules\Core\Console\CustomEloquentCommand;
use Modules\Core\Console\CustomEmptyCacheCommand;
use Modules\Core\Console\CustomEmptyEloquentCommand;
use Modules\Core\Console\CustomEntityCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeCreateCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeCreateTranslatableCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeEditCommand;
use Modules\Core\Console\CustomFolderTranslatableBladeEditTranslatableCommand;
use Modules\Core\Console\CustomFolderTranslatableControllerCommand;
use Modules\Core\Console\CustomFolderTranslatableCreateRequestCommand;
use Modules\Core\Console\CustomFolderTranslatableUpdateRequestCommand;
use Modules\Core\Console\CustomLangCommand;
use Modules\Core\Console\CustomRepositoryCommand;
use Modules\Core\Console\CustomTranslatableBladeCreateCommand;
use Modules\Core\Console\CustomTranslatableBladeCreateTranslatableCommand;
use Modules\Core\Console\CustomTranslatableBladeEditCommand;
use Modules\Core\Console\CustomTranslatableBladeEditTranslatableCommand;
use Modules\Core\Console\CustomTranslatableControllerCommand;
use Modules\Core\Console\CustomTranslatableCreateRequestCommand;
use Modules\Core\Console\CustomTranslatableEntityCommand;
use Modules\Core\Console\CustomTranslatableUpdateRequestCommand;
use Modules\Core\Console\CustomUpdateRequestCommand;
use Modules\Core\Console\PruneOtpCodesCommand;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Drivers\Sms\FakeSmsDriver;
use Modules\Core\Drivers\Sms\LogSmsDriver;
use Modules\Core\Drivers\Sms\Msg91SmsDriver;
use Modules\Core\Foundations\AssetsManager as AssetsManagerInterFace;
use Modules\Core\Foundations\Modules\AssetsManager;
use Modules\Core\Support\IntegrationHttp;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * The filters base class name.
     *
     * @var array
     */
    protected $middleware = [
        'Core' => [
            'can' => 'Authorization',
            'locale' => 'Localization',
            'auth' => 'Authenticate',
            'api.envelope' => 'ApiEnvelope',
        ],
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerMiddleware($this->app['router']);
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->warnOnUnflushableCacheStore();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        IntegrationHttp::register();
        $this->registerRateLimiters();
        $this->registerApiRoutes();
        $this->commands([
            PruneOtpCodesCommand::class,
            CronMakeCommand::class,
            CustomEntityCommand::class,
            CustomRepositoryCommand::class,
            CustomEloquentCommand::class,
            CustomCacheCommand::class,
            CustomLangCommand::class,
            CustomCreateRequestCommand::class,
            CustomUpdateRequestCommand::class,
            CustomControllerCommand::class,
            CustomBladeIndexCommand::class,
            CustomBladeCreateCommand::class,
            CustomBladeEditCommand::class,
            CustomFolderTranslatableBladeCreateCommand::class,
            CustomFolderTranslatableBladeEditCommand::class,
            CustomFolderTranslatableBladeCreateTranslatableCommand::class,
            CustomFolderTranslatableBladeEditTranslatableCommand::class,
            CustomBladeGridCommand::class,
            CustomEmptyEloquentCommand::class,
            CustomEmptyCacheCommand::class,
            CustomTranslatableEntityCommand::class,
            CustomFolderTranslatableControllerCommand::class,
            CustomTranslatableControllerCommand::class,
            CustomTranslatableCreateRequestCommand::class,
            CustomTranslatableUpdateRequestCommand::class,
            CustomFolderTranslatableCreateRequestCommand::class,
            CustomFolderTranslatableUpdateRequestCommand::class,
            CustomTranslatableBladeCreateCommand::class,
            CustomTranslatableBladeCreateTranslatableCommand::class,
            CustomTranslatableBladeEditCommand::class,
            CustomTranslatableBladeEditTranslatableCommand::class,
            MigrationMakeCommand::class,
            ModuleMakeCommand::class,
            // Registering last wins in Artisan's registry, and that - not the
            // module.json "aliases" block - is what actually replaces nwidart's
            // commands. nwidart registers its own commands before it registers
            // module aliases, so the vendor class is already loaded by then and
            // class_alias() is silently skipped. module:seed ran the vendor
            // implementation until this line was added.
            SeedCommand::class,
        ]);
    }

    /**
     * Register the filters.
     *
     * @return void
     */
    public function registerMiddleware(Router $router)
    {
        if ((! config('core.translation')) && (! config('core.translation_front')) && (! config('core.translation_api'))) {
            unset($this->middleware['Core']['locale']);
        }

        foreach ($this->middleware as $module => $middlewares) {
            foreach ($middlewares as $name => $middleware) {
                $class = "Modules\\{$module}\\Http\\Middleware\\{$middleware}";

                $router->aliasMiddleware($name, $class);
            }
        }

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->singleton(AssetsManagerInterFace::class, function () {
            return new AssetsManager;
        });
        $this->app->register(RouteServiceProvider::class);
        // The nwidart aliases live in module.json's "aliases" block, which
        // nwidart applies in Module::register() - before this provider runs.
        // Duplicating them here only gave two places to keep in sync.
        $this->registerCacheStore();
        $this->registerSmsDriver();
    }

    /**
     * Bind the SMS gateway selected by config('core.sms.driver').
     *
     * A singleton, so a test that swaps in FakeSmsDriver sees every message.
     */
    protected function registerSmsDriver(): void
    {
        $this->app->singleton(SmsDriver::class, function ($app): SmsDriver {
            $driver = $app['config']->get('core.sms.driver', 'log');

            return match ($driver) {
                'log' => new LogSmsDriver,
                'fake' => new FakeSmsDriver,
                'msg91' => new Msg91SmsDriver((array) $app['config']->get('core.sms.msg91', [])),
                default => throw new InvalidArgumentException("Unsupported SMS driver [{$driver}]."),
            };
        });
    }

    /**
     * api/{version}/app/config (plan M20). Registered here rather than in Core's RouteServiceProvider,
     * which maps its routes before this provider has merged config('core.*').
     */
    protected function registerApiRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $register = fn () => Route::prefix('api')
            ->middleware('api')
            ->namespace('Modules\\Core\\Http\\Controllers\\Api')
            ->group(__DIR__.'/../../routes/api.php');

        if (config('core.translation_api')) {
            Route::prefix('{locale}')->middleware(['locale'])->group($register);
        } else {
            $register();
        }
    }

    /**
     * Named throttles.
     *
     * "otp" (M03, M08, web forms): 5 a minute and 20 an hour per IP. OtpService enforces its own
     * per-number caps; this stops a single client hammering the route.
     *
     * The REST API limiters (plan M20 §3.4, numbers in config('core.api_limits')):
     *  - api-auth         OTP send: 5/min per phone or email + 20/min per IP
     *  - api-auth-verify  OTP verify: 10/min per phone or email + 20/min per IP
     *  - api              60/min per user, or per IP when anonymous
     *  - api-search       120/min per user or IP
     *  - api-checkout     10/min per user (place order, start payment)
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('otp', fn (Request $request): array => [
            Limit::perMinute(5)->by('otp-minute:'.$request->ip()),
            Limit::perHour((int) config('core.otp.max_sends_per_ip_per_hour', 20))->by('otp-hour:'.$request->ip()),
        ]);

        RateLimiter::for('api-auth', fn (Request $request): array => $this->otpLimits($request, (int) config('core.api_limits.auth_phone', 5)));

        RateLimiter::for('api-auth-verify', fn (Request $request): array => $this->otpLimits($request, (int) config('core.api_limits.auth_verify_phone', 10)));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute((int) config('core.api_limits.api', 60))->by($this->apiKey($request)));

        RateLimiter::for('api-search', fn (Request $request): Limit => Limit::perMinute((int) config('core.api_limits.search', 120))->by($this->apiKey($request)));

        RateLimiter::for('api-checkout', fn (Request $request): Limit => Limit::perMinute((int) config('core.api_limits.checkout', 10))->by($this->apiKey($request)));
    }

    /**
     * Per phone/email and per IP limits for the OTP endpoints.
     *
     * @return array<int, Limit>
     */
    protected function otpLimits(Request $request, int $perIdentifier): array
    {
        $limits = [Limit::perMinute((int) config('core.api_limits.auth_ip', 20))->by('ip:'.$request->ip())];

        $identifier = filled($request->input('email'))
            ? strtolower(trim((string) $request->input('email')))
            : substr((string) preg_replace('/\D+/', '', (string) $request->input('phone')), -10);

        if ($identifier !== '') {
            $limits[] = Limit::perMinute($perIdentifier)->by('id:'.sha1($identifier));
        }

        return $limits;
    }

    protected function apiKey(Request $request): string
    {
        $user = $request->user('api');

        return $user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip();
    }

    /**
     * Bind Core's FileStore as the "file" cache driver.
     *
     * This used to be a class_alias over Illuminate\Cache\FileStore, which only
     * took effect if Core registered before anything autoloaded the real class.
     * When it lost that race the framework store was used instead and every
     * flushCacheFor() silently became a no-op. Cache::extend has no such race.
     *
     * @return void
     */
    protected function registerCacheStore()
    {
        $this->app->booting(function ($app) {
            $app['cache']->extend('file', function ($app, $config) {
                return $app['cache']->repository(
                    (new FileStore(
                        $app['files'],
                        $config['path'],
                        $config['permission'] ?? null,
                        $app['config']['cache.serializable_classes'] ?? null,
                    ))->setLockDirectory($config['lock_path'] ?? null),
                    $config
                );
            });
        });
    }

    /**
     * Warn when the active cache store cannot flush a single entity.
     *
     * Per-entity invalidation needs either tag support or Core's FileStore.
     * config/cache.php defaults to the database driver, which has neither, so
     * an environment that forgets CACHE_STORE=file would serve stale reads
     * until their TTL expired with nothing in the logs to explain it.
     *
     * @return void
     */
    protected function warnOnUnflushableCacheStore()
    {
        $store = $this->app['cache']->store()->getStore();

        if (method_exists($store, 'tags') || $store instanceof FileStore) {
            return;
        }

        logger()->warning(sprintf(
            'Cache store [%s] supports neither tags nor per-entity flushing, so Modules\\Core '
            .'cannot invalidate an entity on write - cached reads will be stale until they expire. '
            .'Use a tag-aware store (redis, memcached, array) or set CACHE_STORE=file.',
            $store::class
        ));
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/core.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php',
            'core'
        );

        $this->publishes([
            __DIR__.'/../../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/translatable.php',
            'translatable'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../../config/api_codes.php',
            'api_codes'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/core');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'core');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/core');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'core');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'core');
        }
    }
}
