<?php

/*
|--------------------------------------------------------------------------
| Public shipment tracking (M13) and the customer's failed-delivery answer
|--------------------------------------------------------------------------
|
| /track (search form) and /track/{awbOrOrderNo}: safe fields only, throttled. Loaded before
| the Pages module's /{slug} catch-all (lower module priority).
|
*/

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/track', [
        'as' => 'tracking.index',
        'uses' => 'TrackingController@index',
    ]);

    Route::get('/track/{awbOrOrderNo}', [
        'as' => 'tracking.show',
        'uses' => 'TrackingController@show',
    ])->where('awbOrOrderNo', '[A-Za-z0-9_-]{3,40}');
});

/*
| The customer's answer to a failed delivery attempt (FR-LOG-008), from the order page.
*/
Route::middleware(['auth:customer', 'throttle:10,1'])->group(function () {
    Route::post('/customer/orders/{orderNo}/delivery-issue/{sellerOrder}', [
        'as' => 'customer.orders.delivery_issue',
        'uses' => 'Account\DeliveryIssueController@store',
    ])->where('sellerOrder', '[0-9]+');
});
