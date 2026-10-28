<?php

/*
|--------------------------------------------------------------------------
| Supplier returns (M15)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/returns..., names "seller.*", middleware
| web + auth:seller + seller.active. Order staff and owners only. Controllers read route
| parameters with $request->route() (the optional {locale} prefix shifts positional ones).
|
*/

Route::middleware('seller.can:orders')->prefix('returns')->group(function () {
    Route::get('/', [
        'as' => 'return.index',
        'uses' => 'SellerReturnController@index',
    ]);
    Route::post('/filters', [
        'as' => 'return.filters',
        'uses' => 'SellerReturnController@filters',
    ]);
    Route::get('/claims/{id}/photos/{index}', [
        'as' => 'return_claim.photo',
        'uses' => 'ReturnClaimController@photo',
    ])->where(['id' => '[0-9]+', 'index' => '[0-9]+']);
    Route::get('/{returnNo}', [
        'as' => 'return.show',
        'uses' => 'SellerReturnController@show',
    ])->where('returnNo', 'RET-[0-9A-Z-]+');
    Route::post('/{returnNo}/qc', [
        'as' => 'return.qc',
        'uses' => 'SellerReturnController@qc',
    ])->where('returnNo', 'RET-[0-9A-Z-]+');
    Route::post('/{returnNo}/claim', [
        'as' => 'return_claim.store',
        'uses' => 'ReturnClaimController@store',
    ])->where('returnNo', 'RET-[0-9A-Z-]+');
    Route::get('/{returnNo}/photos/{image}', [
        'as' => 'return.photo',
        'uses' => 'SellerReturnController@photo',
    ])->where(['returnNo' => 'RET-[0-9A-Z-]+', 'image' => '[0-9]+']);
});
