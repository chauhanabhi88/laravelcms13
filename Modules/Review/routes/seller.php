<?php

/*
|--------------------------------------------------------------------------
| Supplier reviews (M16)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/reviews..., names "seller.*", middleware
| web + auth:seller + seller.active. Catalog staff and owners only. Controllers read route
| parameters with $request->route() (the optional {locale} prefix shifts positional ones).
|
*/

Route::middleware('seller.can:catalog')->prefix('reviews')->group(function () {
    Route::get('/', [
        'as' => 'review.index',
        'uses' => 'SellerReviewController@index',
    ]);
    Route::post('/{id}/reply', [
        'as' => 'review.reply',
        'uses' => 'SellerReviewController@reply',
        'middleware' => 'throttle:30,1',
    ])->where('id', '[0-9]+');
});
