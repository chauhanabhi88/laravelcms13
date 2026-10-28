<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Notification templates (SMS / push / in-app / WhatsApp) and the delivery log. Loaded
| with the admin prefix and the "backend" middleware group.
|
*/

Route::prefix('notification/template')->group(function () {
    Route::get('/', [
        'as' => 'admin.notification_template.index',
        'uses' => 'NotificationTemplateController@index',
        'middleware' => 'can:admin.notification_template.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.notification_template.filters',
        'uses' => 'NotificationTemplateController@filters',
        'middleware' => 'can:admin.notification_template.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.notification_template.create',
        'uses' => 'NotificationTemplateController@create',
        'middleware' => 'can:admin.notification_template.create',
    ]);

    Route::post('/', [
        'as' => 'admin.notification_template.store',
        'uses' => 'NotificationTemplateController@store',
        'middleware' => 'can:admin.notification_template.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.notification_template.edit',
        'uses' => 'NotificationTemplateController@edit',
        'middleware' => 'can:admin.notification_template.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.notification_template.update',
        'uses' => 'NotificationTemplateController@update',
        'middleware' => 'can:admin.notification_template.edit',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.notification_template.update_status',
        'uses' => 'NotificationTemplateController@updateStatus',
        'middleware' => 'can:admin.notification_template.update_status',
    ]);

    Route::post('/{id}/test-send', [
        'as' => 'admin.notification_template.test_send',
        'uses' => 'NotificationTemplateController@testSend',
        'middleware' => 'can:admin.notification_template.test_send',
    ])->where('id', '[0-9]+');

    Route::delete('/delete/{id}', [
        'as' => 'admin.notification_template.delete',
        'uses' => 'NotificationTemplateController@delete',
        'middleware' => 'can:admin.notification_template.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.notification_template.mass_delete',
        'uses' => 'NotificationTemplateController@massDelete',
        'middleware' => 'can:admin.notification_template.mass_delete',
    ]);
});

Route::prefix('notification/log')->group(function () {
    Route::get('/', [
        'as' => 'admin.notification_log.index',
        'uses' => 'NotificationLogController@index',
        'middleware' => 'can:admin.notification_log.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.notification_log.filters',
        'uses' => 'NotificationLogController@filters',
        'middleware' => 'can:admin.notification_log.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.notification_log.show',
        'uses' => 'NotificationLogController@show',
        'middleware' => 'can:admin.notification_log.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/retry', [
        'as' => 'admin.notification_log.retry',
        'uses' => 'NotificationLogController@retry',
        'middleware' => 'can:admin.notification_log.retry',
    ])->where('id', '[0-9]+');
});
