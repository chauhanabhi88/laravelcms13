<?php

/*
|--------------------------------------------------------------------------
| Public shipment tracking (M13)
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
