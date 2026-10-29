<?php

/*
|--------------------------------------------------------------------------
| Coupon API (M18)
|--------------------------------------------------------------------------
|
| api/{version}/cart/coupon (POST apply, DELETE remove) and api/{version}/cart/offers for
| customers (Passport token with the "customer" scope) and guests (X-Cart-Token header), like
| the cart API. Every answer carries the cart and its totals.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/cart')
            ->middleware(['throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::post('/coupon', [
                    'as' => 'cart_api.coupon.apply',
                    'uses' => $upperCaseVersion.'\CouponController@apply',
                    'middleware' => 'throttle:20,1',
                ]);
                Route::delete('/coupon', [
                    'as' => 'cart_api.coupon.remove',
                    'uses' => $upperCaseVersion.'\CouponController@remove',
                ]);
                Route::get('/offers', [
                    'as' => 'cart_api.offers',
                    'uses' => $upperCaseVersion.'\CouponController@offers',
                ]);
            });
    }
}
