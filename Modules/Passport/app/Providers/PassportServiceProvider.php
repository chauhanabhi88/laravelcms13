<?php

namespace Modules\Passport\Providers;

use DateInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Modules\Passport\Grant\PasswordGrant;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Merged during register() so the values are available to anything
        // resolving Passport during boot().
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'passport');
    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerPasswordGrant();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Enable the module's password grant in place of the stock one.
     *
     * Passport::enablePasswordGrant() is deliberately not called: it would
     * register the league grant, which does not report CMS error messages.
     */
    protected function registerPasswordGrant(): void
    {
        // Defaults are repeated here so a stale cached config cannot take the
        // application down with a malformed interval.
        Passport::tokensExpireIn(new DateInterval(config('passport.tokens_expire_in') ?: 'P7D'));
        Passport::refreshTokensExpireIn(new DateInterval(config('passport.refresh_tokens_expire_in') ?: 'P30D'));

        $this->app->afterResolving(AuthorizationServer::class, function (AuthorizationServer $server): void {
            $grant = $this->app->make(PasswordGrant::class);
            $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());

            $server->enableGrantType($grant, Passport::tokensExpireIn());
        });
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/passport.php'),
        ], 'config');
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/passport');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'passport');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'passport');
        }
    }
}
