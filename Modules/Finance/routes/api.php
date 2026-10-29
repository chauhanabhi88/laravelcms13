<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Reseller earnings API (M17, M20)
|--------------------------------------------------------------------------
|
| api/{version}/customer/earnings and .../earnings/withdraw for customers (Passport token with
| the "customer" scope).
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/customer/earnings')
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/', [
                    'as' => 'finance_api.earnings.show',
                    'uses' => $upperCaseVersion.'\EarningsController@show',
                ]);
                Route::post('/withdraw', [
                    'as' => 'finance_api.earnings.withdraw',
                    'uses' => $upperCaseVersion.'\EarningsController@withdraw',
                    'middleware' => 'throttle:api-checkout',
                ]);
            });
    }
}
