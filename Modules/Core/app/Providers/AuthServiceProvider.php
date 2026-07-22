<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Laravel\Passport\Passport;


class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(GateContract $gate)
    {
        Passport::tokensCan([
            'customer' => 'Access customer APIs',
            'admin'    => 'Access admin APIs',
        ]);
        $this->registerPolicies($gate);

        $gate->before(function($user, $ability) use ($gate) {
            if (method_exists($user, 'hasPermission')) {
                return $user->hasPermission($ability);
            }
        });
    }
}
