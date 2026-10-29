<?php

/*
|--------------------------------------------------------------------------
| Supplier coupons (M18)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/coupons..., names "seller.*", middleware
| web + auth:seller + seller.active. Catalog staff and owners only. A seller's coupons are funded
| by the seller and limited to their own products. Controllers read route parameters with
| $request->route() (the optional {locale} prefix shifts positional ones).
|
*/

Route::middleware('seller.can:catalog')->prefix('coupons')->group(function () {
    Route::get('/', [
        'as' => 'coupon.index',
        'uses' => 'SellerCouponController@index',
    ]);
    Route::get('/create', [
        'as' => 'coupon.create',
        'uses' => 'SellerCouponController@create',
    ]);
    Route::post('/', [
        'as' => 'coupon.store',
        'uses' => 'SellerCouponController@store',
        'middleware' => 'throttle:20,1',
    ]);
    Route::get('/{id}/edit', [
        'as' => 'coupon.edit',
        'uses' => 'SellerCouponController@edit',
    ])->where('id', '[0-9]+');
    Route::put('/{id}', [
        'as' => 'coupon.update',
        'uses' => 'SellerCouponController@update',
        'middleware' => 'throttle:20,1',
    ])->where('id', '[0-9]+');
    Route::post('/{id}/toggle', [
        'as' => 'coupon.toggle',
        'uses' => 'SellerCouponController@toggle',
    ])->where('id', '[0-9]+');
});
