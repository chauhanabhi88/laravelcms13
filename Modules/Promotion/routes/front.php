<?php

/*
|--------------------------------------------------------------------------
| Coupons in the cart and checkout, referrals (M18)
|--------------------------------------------------------------------------
|
| The coupon actions work on the visitor's cart (guest or customer) and answer like the cart
| actions: JSON to fetch() ({success, message, count, html?}), else a redirect back. The share
| link /r/{code} remembers the referral code for sign-up; the referral page needs a login.
|
*/

Route::post('/cart/coupon', [
    'as' => 'cart.coupon.apply',
    'uses' => 'CouponController@apply',
    'middleware' => 'throttle:20,1',
]);

Route::delete('/cart/coupon', [
    'as' => 'cart.coupon.remove',
    'uses' => 'CouponController@remove',
    'middleware' => 'throttle:30,1',
]);

Route::get('/cart/offers', [
    'as' => 'cart.offers',
    'uses' => 'CouponController@offers',
    'middleware' => 'throttle:60,1',
]);

Route::get('/r/{code}', [
    'as' => 'referral.landing',
    'uses' => 'ReferralController@landing',
    'middleware' => 'throttle:30,1',
])->where('code', '[A-Za-z0-9]{4,20}');

Route::get('/customer/referral', [
    'as' => 'customer.referral.index',
    'uses' => 'Account\ReferralController@index',
    'middleware' => 'auth:customer',
]);
