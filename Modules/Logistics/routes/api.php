<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Logistics API (M13)
|--------------------------------------------------------------------------
|
| api/{version}/orders/{orderNo}/tracking for customers (Passport token with the "customer"
| scope); another customer's order is a 404. The delivery check (api/{version}/delivery-check)
| stays in the Storefront module and is backed by the Logistics estimator.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version)
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/orders/{orderNo}/tracking', [
                    'as' => 'logistics_api.orders.tracking',
                    'uses' => $upperCaseVersion.'\TrackingController@show',
                ]);
            });
    }
}
