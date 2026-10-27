<?php

/*
|--------------------------------------------------------------------------
| Supplier panel routes
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /{seller.panel_prefix}/..., names "seller.*",
| middleware web + auth:seller + seller.active (onboarded, read-only unless active).
| Staff areas use seller.can:<role>; owners pass every check.
|
*/

Route::get('/dashboard', [
    'as' => 'dashboard',
    'uses' => 'DashboardController@index',
]);

Route::prefix('settings')->group(function () {
    Route::middleware('seller.can:owner')->group(function () {
        Route::get('/business', [
            'as' => 'profile.edit',
            'uses' => 'ProfileController@edit',
        ]);
        Route::put('/business', [
            'as' => 'profile.update',
            'uses' => 'ProfileController@update',
        ]);

        Route::get('/staff', [
            'as' => 'staff.index',
            'uses' => 'StaffController@index',
        ]);
        Route::get('/staff/create', [
            'as' => 'staff.create',
            'uses' => 'StaffController@create',
        ]);
        Route::post('/staff', [
            'as' => 'staff.store',
            'uses' => 'StaffController@store',
        ]);
        Route::get('/staff/{staff}/edit', [
            'as' => 'staff.edit',
            'uses' => 'StaffController@edit',
        ])->where('staff', '[0-9]+');
        Route::put('/staff/{staff}', [
            'as' => 'staff.update',
            'uses' => 'StaffController@update',
        ])->where('staff', '[0-9]+');
        Route::delete('/staff/{staff}', [
            'as' => 'staff.destroy',
            'uses' => 'StaffController@destroy',
        ])->where('staff', '[0-9]+');
    });

    Route::middleware('seller.can:finance')->group(function () {
        Route::get('/bank', [
            'as' => 'bank.index',
            'uses' => 'BankAccountController@index',
        ]);
        Route::post('/bank', [
            'as' => 'bank.store',
            'uses' => 'BankAccountController@store',
        ]);
        Route::post('/bank/{account}/verify', [
            'as' => 'bank.verify',
            'uses' => 'BankAccountController@verify',
        ])->where('account', '[0-9]+');
    });

    Route::middleware('seller.can:orders')->group(function () {
        Route::get('/warehouses', [
            'as' => 'warehouse.index',
            'uses' => 'WarehouseController@index',
        ]);
        Route::get('/warehouses/create', [
            'as' => 'warehouse.create',
            'uses' => 'WarehouseController@create',
        ]);
        Route::post('/warehouses', [
            'as' => 'warehouse.store',
            'uses' => 'WarehouseController@store',
        ]);
        Route::get('/warehouses/{warehouse}/edit', [
            'as' => 'warehouse.edit',
            'uses' => 'WarehouseController@edit',
        ])->where('warehouse', '[0-9]+');
        Route::put('/warehouses/{warehouse}', [
            'as' => 'warehouse.update',
            'uses' => 'WarehouseController@update',
        ])->where('warehouse', '[0-9]+');
        Route::post('/warehouses/{warehouse}/default', [
            'as' => 'warehouse.default',
            'uses' => 'WarehouseController@makeDefault',
        ])->where('warehouse', '[0-9]+');
        Route::delete('/warehouses/{warehouse}', [
            'as' => 'warehouse.destroy',
            'uses' => 'WarehouseController@destroy',
        ])->where('warehouse', '[0-9]+');
    });
});
