<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Returns API (M15)
|--------------------------------------------------------------------------
|
| api/{version}/orders/{orderNo}/returnable-items and api/{version}/returns[/{returnNo}[/cancel]]
| for customers (Passport token with the "customer" scope). POST /returns is multipart (photos[]).
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version)
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/orders/{orderNo}/returnable-items', [
                    'as' => 'returns_api.returnable_items',
                    'uses' => $upperCaseVersion.'\ReturnController@returnableItems',
                ]);
                Route::get('/returns', [
                    'as' => 'returns_api.index',
                    'uses' => $upperCaseVersion.'\ReturnController@index',
                ]);
                Route::post('/returns', [
                    'as' => 'returns_api.store',
                    'uses' => $upperCaseVersion.'\ReturnController@store',
                    'middleware' => 'throttle:10,1',
                ]);
                Route::get('/returns/{returnNo}', [
                    'as' => 'returns_api.show',
                    'uses' => $upperCaseVersion.'\ReturnController@show',
                ]);
                Route::post('/returns/{returnNo}/cancel', [
                    'as' => 'returns_api.cancel',
                    'uses' => $upperCaseVersion.'\ReturnController@cancel',
                    'middleware' => 'throttle:10,1',
                ]);
            });
    }
}
