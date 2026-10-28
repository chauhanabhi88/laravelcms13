<?php

/*
|--------------------------------------------------------------------------
| Supplier panel order desk routes (M14)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/..., names "seller.*", middleware
| web + auth:seller + seller.active (writes 403 for suspended sellers). Order staff and
| owners only (seller.can:orders). Controllers read route parameters with $request->route()
| because the optional {locale} prefix shifts positional arguments. The manifest routes
| (/orders/manifests...) live in the Logistics module.
|
*/

Route::middleware('seller.can:orders')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [
            'as' => 'order.index',
            'uses' => 'SellerOrderController@index',
        ]);
        Route::post('/filters', [
            'as' => 'order.filters',
            'uses' => 'SellerOrderController@filters',
        ]);
        Route::get('/export', [
            'as' => 'order.export',
            'uses' => 'OrderExportController@export',
        ]);
        Route::post('/accept', [
            'as' => 'order.accept',
            'uses' => 'SellerOrderController@accept',
        ]);
        Route::post('/pack', [
            'as' => 'order.pack',
            'uses' => 'SellerOrderController@pack',
        ]);

        Route::post('/labels', [
            'as' => 'label.generate',
            'uses' => 'LabelController@generate',
        ]);
        Route::get('/labels/{batch}', [
            'as' => 'label.download',
            'uses' => 'LabelController@download',
        ])->where('batch', '[0-9]+');
        Route::get('/labels/{batch}/status', [
            'as' => 'label.status',
            'uses' => 'LabelController@status',
        ])->where('batch', '[0-9]+');

        Route::get('/documents/{document}', [
            'as' => 'order.document',
            'uses' => 'SellerOrderController@document',
        ])->where('document', '[0-9]+');

        Route::post('/{id}/cancel', [
            'as' => 'order.cancel',
            'uses' => 'SellerOrderController@cancel',
        ])->where('id', '[0-9]+');
        Route::post('/{id}/return-receipt', [
            'as' => 'order.return_receipt',
            'uses' => 'ReturnReceiptController@store',
        ])->where('id', '[0-9]+');

        // Sub-order numbers are upper case (ORD-2610-000123-1), so /orders/manifests never matches.
        Route::get('/{subOrderNo}', [
            'as' => 'order.show',
            'uses' => 'SellerOrderController@show',
        ])->where('subOrderNo', '[A-Z][A-Z0-9]*-[A-Z0-9-]+');
    });

    Route::get('/settings/orders', [
        'as' => 'settings.orders',
        'uses' => 'OrderSettingsController@edit',
    ]);
    Route::post('/settings/orders', [
        'as' => 'settings.orders.update',
        'uses' => 'OrderSettingsController@update',
    ]);
});
