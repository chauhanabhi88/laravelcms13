<?php

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| "Sell with Us" landing at /{seller.panel_prefix}. The public store page /s/{storeSlug}
| (route "storefront.store.show") belongs to the Storefront module since M06.
|
*/

Route::get('/'.trim((string) config('seller.panel_prefix', 'supplier'), '/'), [
    'as' => 'seller.landing',
    'uses' => 'SupplierLandingController@index',
]);
