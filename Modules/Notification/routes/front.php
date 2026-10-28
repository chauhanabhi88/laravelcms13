<?php

/*
|--------------------------------------------------------------------------
| Customer inbox & preferences (M09)
|--------------------------------------------------------------------------
|
| Under the Customer module's /customer prefix (kept since M08). The feed is the bell's
| JSON source; read / read-all answer JSON to fetch() and redirect otherwise.
|
*/

Route::prefix('customer/notifications')->middleware('auth:customer')->group(function () {
    Route::get('/', [
        'as' => 'customer.notifications.index',
        'uses' => 'InboxController@index',
    ]);
    Route::get('/feed', [
        'as' => 'customer.notifications.feed',
        'uses' => 'InboxController@feed',
        'middleware' => 'throttle:60,1',
    ]);
    Route::post('/read-all', [
        'as' => 'customer.notifications.read_all',
        'uses' => 'InboxController@readAll',
    ]);
    Route::post('/{id}/read', [
        'as' => 'customer.notifications.read',
        'uses' => 'InboxController@read',
    ]);
    Route::get('/settings', [
        'as' => 'customer.notification_preferences.edit',
        'uses' => 'PreferenceController@edit',
    ]);
    Route::post('/settings', [
        'as' => 'customer.notification_preferences.update',
        'uses' => 'PreferenceController@update',
    ]);
});
