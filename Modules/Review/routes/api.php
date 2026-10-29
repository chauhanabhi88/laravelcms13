<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Reviews API (M16)
|--------------------------------------------------------------------------
|
| Public: api/{version}/products/{id}/reviews (?rating=&with_photos=1&sort=). Customers
| (Passport token with the "customer" scope): api/{version}/reviews (multipart, photos[]),
| api/{version}/reviews/mine (written + waiting) and api/{version}/reviews/{id}/helpful.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version)->middleware('api.envelope')->group(function () use ($upperCaseVersion) {
            Route::get('/products/{id}/reviews', [
                'as' => 'review_api.index',
                'uses' => $upperCaseVersion.'\ReviewController@index',
                'middleware' => 'throttle:api',
            ])->where('id', '[0-9]+');

            Route::middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api'])->group(function () use ($upperCaseVersion) {
                Route::get('/reviews/mine', [
                    'as' => 'review_api.mine',
                    'uses' => $upperCaseVersion.'\ReviewController@mine',
                ]);
                Route::post('/reviews', [
                    'as' => 'review_api.store',
                    'uses' => $upperCaseVersion.'\ReviewController@store',
                    'middleware' => 'throttle:10,1',
                ]);
                Route::post('/reviews/{id}/helpful', [
                    'as' => 'review_api.vote',
                    'uses' => $upperCaseVersion.'\ReviewController@vote',
                    'middleware' => 'throttle:30,1',
                ])->where('id', '[0-9]+');
            });
        });
    }
}
