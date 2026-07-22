<?php

namespace Modules\Passport\Providers;

use Laravel\Passport\Passport;
use Modules\Passport\Models\Token;
use Illuminate\Support\ServiceProvider;
use Modules\Passport\Grant\PasswordGrant;
use League\OAuth2\Server\AuthorizationServer;
use Modules\Passport\Repositories\ClientRepository;
use Laravel\Passport\Bridge\ClientRepository as PassportClientRepository;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        /* Passport::enablePasswordGrant();
        Passport::useTokenModel(Token::class);
        
        if($scopes = config('passport.scopes')) {
            Passport::tokensCan(
                $scopes
            );
        } */

        // Register our custom grant
        $this->app->afterResolving(AuthorizationServer::class, function (AuthorizationServer $server) {
            $grant = app(PasswordGrant::class);
            $grant->setRefreshTokenTTL(new \DateInterval('P30D'));

            $server->enableGrantType(
                $grant,
                new \DateInterval('P7D')
            );
        });

        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(PassportClientRepository::class, function($app) {
            return app(ClientRepository::class);
        });        
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/passport.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'passport'
        );
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/passport');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'passport');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'passport');
        }
    }
    
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}