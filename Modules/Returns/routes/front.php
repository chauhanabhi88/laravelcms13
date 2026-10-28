<?php

/*
|--------------------------------------------------------------------------
| Customer returns (M15)
|--------------------------------------------------------------------------
|
| Signed-in customers only, next to My Orders under /customer. The wizard keeps its draft in
| the session: step 1 (items, reason, type) starts from an order, photos and refund details
| follow, then the confirmation page submits. Return numbers are upper case (RET-2611-000045),
| so /customer/returns/draft/... never matches {returnNo}.
|
*/

Route::middleware('auth:customer')->group(function () {
    Route::get('/customer/orders/{orderNo}/return', [
        'as' => 'customer.returns.create',
        'uses' => 'Account\ReturnController@create',
    ]);

    Route::prefix('customer/returns')->group(function () {
        Route::get('/', [
            'as' => 'customer.returns.index',
            'uses' => 'Account\ReturnController@index',
        ]);

        Route::post('/', [
            'as' => 'customer.returns.submit',
            'uses' => 'Account\ReturnController@submit',
            'middleware' => 'throttle:10,1',
        ]);

        Route::get('/draft/photo/{name}', [
            'as' => 'customer.returns.draft_photo',
            'uses' => 'Account\ReturnController@draftPhoto',
        ])->where('name', '[A-Za-z0-9]{40}\.jpg');

        Route::get('/draft/{step}', [
            'as' => 'customer.returns.step',
            'uses' => 'Account\ReturnController@step',
        ])->where('step', 'photos|refund|confirm');

        Route::post('/draft/{step}', [
            'as' => 'customer.returns.store_step',
            'uses' => 'Account\ReturnController@storeStep',
            'middleware' => 'throttle:30,1',
        ])->where('step', 'reason|photos|refund');

        Route::get('/{returnNo}', [
            'as' => 'customer.returns.show',
            'uses' => 'Account\ReturnController@show',
        ])->where('returnNo', 'RET-[0-9A-Z-]+');

        Route::post('/{returnNo}/cancel', [
            'as' => 'customer.returns.cancel',
            'uses' => 'Account\ReturnController@cancel',
            'middleware' => 'throttle:10,1',
        ])->where('returnNo', 'RET-[0-9A-Z-]+');

        Route::get('/{returnNo}/photos/{image}', [
            'as' => 'customer.returns.photo',
            'uses' => 'Account\ReturnController@photo',
        ])->where(['returnNo' => 'RET-[0-9A-Z-]+', 'image' => '[0-9]+']);
    });
});
