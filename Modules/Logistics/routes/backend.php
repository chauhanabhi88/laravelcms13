<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Logistics admin (plan M13 §6): couriers (+ connection test), courier rules (+ simulator),
| shipments (detail, book, resync, cancel, manual AWB / scans, export) and the NDR queue.
| Loaded with the admin prefix and the "backend" group.
|
*/

Route::prefix('logistics/courier')->group(function () {
    Route::get('/', [
        'as' => 'admin.courier.index',
        'uses' => 'CourierController@index',
        'middleware' => 'can:admin.courier.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.courier.filters',
        'uses' => 'CourierController@filters',
        'middleware' => 'can:admin.courier.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.courier.create',
        'uses' => 'CourierController@create',
        'middleware' => 'can:admin.courier.create',
    ]);

    Route::post('/', [
        'as' => 'admin.courier.store',
        'uses' => 'CourierController@store',
        'middleware' => 'can:admin.courier.store',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.courier.edit',
        'uses' => 'CourierController@edit',
        'middleware' => 'can:admin.courier.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.courier.update',
        'uses' => 'CourierController@update',
        'middleware' => 'can:admin.courier.update',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.courier.update_status',
        'uses' => 'CourierController@updateStatus',
        'middleware' => 'can:admin.courier.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.courier.delete',
        'uses' => 'CourierController@delete',
        'middleware' => 'can:admin.courier.delete',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/test-connection', [
        'as' => 'admin.courier.test_connection',
        'uses' => 'CourierController@testConnection',
        'middleware' => ['can:admin.courier.test_connection', 'throttle:10,1'],
    ])->where('id', '[0-9]+');
});

Route::prefix('logistics/courier-rule')->group(function () {
    Route::get('/', [
        'as' => 'admin.courier_rule.index',
        'uses' => 'CourierRuleController@index',
        'middleware' => 'can:admin.courier_rule.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.courier_rule.filters',
        'uses' => 'CourierRuleController@filters',
        'middleware' => 'can:admin.courier_rule.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.courier_rule.create',
        'uses' => 'CourierRuleController@create',
        'middleware' => 'can:admin.courier_rule.create',
    ]);

    Route::post('/', [
        'as' => 'admin.courier_rule.store',
        'uses' => 'CourierRuleController@store',
        'middleware' => 'can:admin.courier_rule.store',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.courier_rule.edit',
        'uses' => 'CourierRuleController@edit',
        'middleware' => 'can:admin.courier_rule.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.courier_rule.update',
        'uses' => 'CourierRuleController@update',
        'middleware' => 'can:admin.courier_rule.update',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.courier_rule.update_status',
        'uses' => 'CourierRuleController@updateStatus',
        'middleware' => 'can:admin.courier_rule.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.courier_rule.delete',
        'uses' => 'CourierRuleController@delete',
        'middleware' => 'can:admin.courier_rule.delete',
    ])->where('id', '[0-9]+');

    Route::match(['get', 'post'], '/simulate', [
        'as' => 'admin.courier_rule.simulate',
        'uses' => 'CourierRuleController@simulate',
        'middleware' => ['can:admin.courier_rule.simulate', 'throttle:30,1'],
    ]);
});

Route::prefix('logistics/shipment')->group(function () {
    Route::get('/', [
        'as' => 'admin.shipment.index',
        'uses' => 'ShipmentController@index',
        'middleware' => 'can:admin.shipment.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.shipment.filters',
        'uses' => 'ShipmentController@filters',
        'middleware' => 'can:admin.shipment.filters',
    ]);

    Route::get('/export', [
        'as' => 'admin.shipment.export',
        'uses' => 'ShipmentController@export',
        'middleware' => 'can:admin.shipment.export',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.shipment.show',
        'uses' => 'ShipmentController@show',
        'middleware' => 'can:admin.shipment.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/book', [
        'as' => 'admin.shipment.book',
        'uses' => 'ShipmentController@book',
        'middleware' => ['can:admin.shipment.book', 'throttle:30,1'],
    ])->where('id', '[0-9]+');

    Route::post('/{id}/resync', [
        'as' => 'admin.shipment.resync',
        'uses' => 'ShipmentController@resync',
        'middleware' => ['can:admin.shipment.resync', 'throttle:30,1'],
    ])->where('id', '[0-9]+');

    Route::post('/{id}/cancel', [
        'as' => 'admin.shipment.cancel',
        'uses' => 'ShipmentController@cancel',
        'middleware' => 'can:admin.shipment.cancel',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/manual-awb', [
        'as' => 'admin.shipment.manual_awb',
        'uses' => 'ShipmentController@manualAwb',
        'middleware' => 'can:admin.shipment.manual_awb',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/manual-status', [
        'as' => 'admin.shipment.manual_status',
        'uses' => 'ShipmentController@manualStatus',
        'middleware' => 'can:admin.shipment.manual_status',
    ])->where('id', '[0-9]+');
});

Route::prefix('logistics/ndr')->group(function () {
    Route::get('/', [
        'as' => 'admin.ndr.index',
        'uses' => 'NdrController@index',
        'middleware' => 'can:admin.ndr.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.ndr.filters',
        'uses' => 'NdrController@filters',
        'middleware' => 'can:admin.ndr.filters',
    ]);

    Route::post('/{id}/act', [
        'as' => 'admin.ndr.act',
        'uses' => 'NdrController@act',
        'middleware' => 'can:admin.ndr.act',
    ])->where('id', '[0-9]+');
});
