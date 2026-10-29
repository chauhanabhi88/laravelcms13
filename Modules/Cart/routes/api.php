<?php

/*
|--------------------------------------------------------------------------
| Cart API (M10)
|--------------------------------------------------------------------------
|
| api/{version}/cart... for customers (Passport token with the "customer" scope) and guests
| (X-Cart-Token header). Authentication is optional here: the controller resolves the owner.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/cart')
            ->middleware(['throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/', [
                    'as' => 'cart_api.show',
                    'uses' => $upperCaseVersion.'\CartController@show',
                ]);
                Route::post('/items', [
                    'as' => 'cart_api.items.store',
                    'uses' => $upperCaseVersion.'\CartController@store',
                ]);
                Route::patch('/items/{id}', [
                    'as' => 'cart_api.items.update',
                    'uses' => $upperCaseVersion.'\CartController@update',
                ])->where('id', '[0-9]+');
                Route::delete('/items/{id}', [
                    'as' => 'cart_api.items.destroy',
                    'uses' => $upperCaseVersion.'\CartController@destroy',
                ])->where('id', '[0-9]+');
                Route::post('/buy-now', [
                    'as' => 'cart_api.buy_now',
                    'uses' => $upperCaseVersion.'\CartController@buyNow',
                ]);
            });
    }
}
