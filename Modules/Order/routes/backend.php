<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Order management (FR-ORD-008): orders grid, detail, export, cancellation, internal notes,
| notification resend, and the seller orders grid. Loaded with the admin prefix and the
| "backend" group.
|
*/

Route::prefix('sales-order')->group(function () {
    Route::get('/', [
        'as' => 'admin.sales_order.index',
        'uses' => 'SalesOrderController@index',
        'middleware' => 'can:admin.sales_order.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.sales_order.filters',
        'uses' => 'SalesOrderController@filters',
        'middleware' => 'can:admin.sales_order.filters',
    ]);

    Route::get('/export', [
        'as' => 'admin.sales_order.export',
        'uses' => 'SalesOrderController@export',
        'middleware' => 'can:admin.sales_order.export',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.sales_order.show',
        'uses' => 'SalesOrderController@show',
        'middleware' => 'can:admin.sales_order.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/cancel', [
        'as' => 'admin.sales_order.cancel',
        'uses' => 'SalesOrderController@cancel',
        'middleware' => 'can:admin.sales_order.cancel',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/note', [
        'as' => 'admin.sales_order.note',
        'uses' => 'SalesOrderController@note',
        'middleware' => 'can:admin.sales_order.note',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/resend-notification', [
        'as' => 'admin.sales_order.resend_notification',
        'uses' => 'SalesOrderController@resendNotification',
        'middleware' => 'can:admin.sales_order.resend_notification',
    ])->where('id', '[0-9]+');
});

Route::prefix('seller-order')->group(function () {
    Route::get('/', [
        'as' => 'admin.seller_order.index',
        'uses' => 'SellerOrderController@index',
        'middleware' => 'can:admin.seller_order.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.seller_order.filters',
        'uses' => 'SellerOrderController@filters',
        'middleware' => 'can:admin.seller_order.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.seller_order.show',
        'uses' => 'SellerOrderController@show',
        'middleware' => 'can:admin.seller_order.show',
    ])->where('id', '[0-9]+');
});

Route::post('seller-order/{id}/force-status', [
    'as' => 'admin.seller_order.force_status',
    'uses' => 'SellerOrderController@forceStatus',
    'middleware' => 'can:admin.seller_order.force_status',
])->where('id', '[0-9]+');
