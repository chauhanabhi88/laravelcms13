<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Returns console (FR-RET-008) and seller claims (FR-RET-006). Loaded with the admin prefix
| and the "backend" group. Photos are served under the show permission.
|
*/

Route::prefix('return-request')->group(function () {
    Route::get('/', [
        'as' => 'admin.return_request.index',
        'uses' => 'ReturnRequestController@index',
        'middleware' => 'can:admin.return_request.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.return_request.filters',
        'uses' => 'ReturnRequestController@filters',
        'middleware' => 'can:admin.return_request.filters',
    ]);

    Route::get('/export', [
        'as' => 'admin.return_request.export',
        'uses' => 'ReturnRequestController@export',
        'middleware' => 'can:admin.return_request.export',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.return_request.show',
        'uses' => 'ReturnRequestController@show',
        'middleware' => 'can:admin.return_request.show',
    ])->where('id', '[0-9]+');

    Route::get('/{id}/photos/{image}', [
        'as' => 'admin.return_request.photo',
        'uses' => 'ReturnRequestController@photo',
        'middleware' => 'can:admin.return_request.show',
    ])->where(['id' => '[0-9]+', 'image' => '[0-9]+']);

    Route::post('/{id}/approve', [
        'as' => 'admin.return_request.approve',
        'uses' => 'ReturnRequestController@approve',
        'middleware' => 'can:admin.return_request.approve',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/reject', [
        'as' => 'admin.return_request.reject',
        'uses' => 'ReturnRequestController@reject',
        'middleware' => 'can:admin.return_request.reject',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/schedule-pickup', [
        'as' => 'admin.return_request.schedule_pickup',
        'uses' => 'ReturnRequestController@schedulePickup',
        'middleware' => 'can:admin.return_request.schedule_pickup',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/force-refund', [
        'as' => 'admin.return_request.force_refund',
        'uses' => 'ReturnRequestController@forceRefund',
        'middleware' => 'can:admin.return_request.force_refund',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/close', [
        'as' => 'admin.return_request.close',
        'uses' => 'ReturnRequestController@close',
        'middleware' => 'can:admin.return_request.close',
    ])->where('id', '[0-9]+');
});

Route::prefix('return-claim')->group(function () {
    Route::get('/', [
        'as' => 'admin.return_claim.index',
        'uses' => 'ReturnClaimController@index',
        'middleware' => 'can:admin.return_claim.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.return_claim.filters',
        'uses' => 'ReturnClaimController@filters',
        'middleware' => 'can:admin.return_claim.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.return_claim.show',
        'uses' => 'ReturnClaimController@show',
        'middleware' => 'can:admin.return_claim.show',
    ])->where('id', '[0-9]+');

    Route::get('/{id}/photos/{index}', [
        'as' => 'admin.return_claim.photo',
        'uses' => 'ReturnClaimController@photo',
        'middleware' => 'can:admin.return_claim.show',
    ])->where(['id' => '[0-9]+', 'index' => '[0-9]+']);

    Route::post('/{id}/decide', [
        'as' => 'admin.return_claim.decide',
        'uses' => 'ReturnClaimController@decide',
        'middleware' => 'can:admin.return_claim.decide',
    ])->where('id', '[0-9]+');
});
