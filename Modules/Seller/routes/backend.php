<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Seller administration: the KYC application queue and all sellers. Loaded with the
| admin prefix and the "backend" middleware group.
|
*/

Route::prefix('seller/application')->group(function () {
    Route::get('/', [
        'as' => 'admin.seller_application.index',
        'uses' => 'SellerApplicationController@index',
        'middleware' => 'can:admin.seller_application.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.seller_application.filters',
        'uses' => 'SellerApplicationController@filters',
        'middleware' => 'can:admin.seller_application.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.seller_application.show',
        'uses' => 'SellerApplicationController@show',
        'middleware' => 'can:admin.seller_application.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/approve', [
        'as' => 'admin.seller_application.approve',
        'uses' => 'SellerApplicationController@approve',
        'middleware' => 'can:admin.seller_application.approve',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/reject', [
        'as' => 'admin.seller_application.reject',
        'uses' => 'SellerApplicationController@reject',
        'middleware' => 'can:admin.seller_application.reject',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/request-changes', [
        'as' => 'admin.seller_application.request_changes',
        'uses' => 'SellerApplicationController@requestChanges',
        'middleware' => 'can:admin.seller_application.request_changes',
    ])->where('id', '[0-9]+');
});

Route::prefix('seller')->group(function () {
    Route::get('/', [
        'as' => 'admin.seller.index',
        'uses' => 'SellerController@index',
        'middleware' => 'can:admin.seller.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.seller.filters',
        'uses' => 'SellerController@filters',
        'middleware' => 'can:admin.seller.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.seller.show',
        'uses' => 'SellerController@show',
        'middleware' => 'can:admin.seller.show',
    ])->where('id', '[0-9]+');

    Route::get('/{id}/edit', [
        'as' => 'admin.seller.edit',
        'uses' => 'SellerController@edit',
        'middleware' => 'can:admin.seller.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.seller.update',
        'uses' => 'SellerController@update',
        'middleware' => 'can:admin.seller.edit',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/suspend', [
        'as' => 'admin.seller.suspend',
        'uses' => 'SellerController@suspend',
        'middleware' => 'can:admin.seller.suspend',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/reactivate', [
        'as' => 'admin.seller.reactivate',
        'uses' => 'SellerController@reactivate',
        'middleware' => 'can:admin.seller.reactivate',
    ])->where('id', '[0-9]+');

    Route::get('/{id}/kyc/{document}', [
        'as' => 'admin.seller.kyc_view',
        'uses' => 'SellerController@kycView',
        'middleware' => 'can:admin.seller.kyc_view',
    ])->where(['id' => '[0-9]+', 'document' => '[0-9]+']);

    Route::post('/{id}/bank/{account}/verify', [
        'as' => 'admin.seller.bank_verify_manual',
        'uses' => 'SellerController@verifyBankManually',
        'middleware' => 'can:admin.seller.bank_verify_manual',
    ])->where(['id' => '[0-9]+', 'account' => '[0-9]+']);
});
