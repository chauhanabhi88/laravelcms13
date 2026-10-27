<?php

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| "Sell with Us" landing at /{seller.panel_prefix} and the public store page. Products on
| the store page arrive with the storefront (M06).
|
*/

Route::get('/'.trim((string) config('seller.panel_prefix', 'supplier'), '/'), [
    'as' => 'seller.landing',
    'uses' => 'SupplierLandingController@index',
]);

Route::get('/s/{storeSlug}', [
    'as' => 'storefront.store.show',
    'uses' => 'StoreController@show',
])->where('storeSlug', '[a-z0-9]+(?:-[a-z0-9]+)*');
