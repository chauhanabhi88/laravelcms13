<?php

/*
|--------------------------------------------------------------------------
| Supplier panel inbox & preferences (M09)
|--------------------------------------------------------------------------
|
| Loaded under /supplier with the "seller." name prefix and web + auth:seller +
| seller.onboarded (see RouteServiceProvider::mapSellerInboxRoutes()).
|
*/

Route::prefix('notifications')->group(function () {
    Route::get('/', [
        'as' => 'notifications.index',
        'uses' => 'InboxController@index',
    ]);
    Route::get('/feed', [
        'as' => 'notifications.feed',
        'uses' => 'InboxController@feed',
        'middleware' => 'throttle:60,1',
    ]);
    Route::post('/read-all', [
        'as' => 'notifications.read_all',
        'uses' => 'InboxController@readAll',
    ]);
    Route::post('/{id}/read', [
        'as' => 'notifications.read',
        'uses' => 'InboxController@read',
    ]);
});

Route::get('/settings/notifications', [
    'as' => 'notification_preferences.edit',
    'uses' => 'PreferenceController@edit',
]);
Route::put('/settings/notifications', [
    'as' => 'notification_preferences.update',
    'uses' => 'PreferenceController@update',
]);
