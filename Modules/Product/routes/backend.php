<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Product administration: all products (detail, block / unblock, export), the catalog QC
| queue and the inventory movements ledger. Loaded with the admin prefix and the
| "backend" middleware group.
|
*/

Route::prefix('product')->group(function () {
    Route::get('/', [
        'as' => 'admin.product.index',
        'uses' => 'ProductController@index',
        'middleware' => 'can:admin.product.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.product.filters',
        'uses' => 'ProductController@filters',
        'middleware' => 'can:admin.product.filters',
    ]);

    Route::get('/export', [
        'as' => 'admin.product.export',
        'uses' => 'ProductController@export',
        'middleware' => 'can:admin.product.export',
    ]);

    Route::get('/qc', [
        'as' => 'admin.product_qc.index',
        'uses' => 'ProductQcController@index',
        'middleware' => 'can:admin.product_qc.index',
    ]);

    Route::post('/qc/filters', [
        'as' => 'admin.product_qc.filters',
        'uses' => 'ProductQcController@filters',
        'middleware' => 'can:admin.product_qc.filters',
    ]);

    Route::post('/qc/bulk', [
        'as' => 'admin.product_qc.bulk',
        'uses' => 'ProductQcController@bulk',
        'middleware' => 'can:admin.product_qc.bulk',
    ]);

    Route::get('/qc/{id}', [
        'as' => 'admin.product_qc.review',
        'uses' => 'ProductQcController@review',
        'middleware' => 'can:admin.product_qc.review',
    ])->where('id', '[0-9]+');

    Route::post('/qc/{id}/approve', [
        'as' => 'admin.product_qc.approve',
        'uses' => 'ProductQcController@approve',
        'middleware' => 'can:admin.product_qc.approve',
    ])->where('id', '[0-9]+');

    Route::post('/qc/{id}/reject', [
        'as' => 'admin.product_qc.reject',
        'uses' => 'ProductQcController@reject',
        'middleware' => 'can:admin.product_qc.reject',
    ])->where('id', '[0-9]+');

    Route::get('/inventory-movements', [
        'as' => 'admin.inventory_movement.index',
        'uses' => 'InventoryMovementController@index',
        'middleware' => 'can:admin.inventory_movement.index',
    ]);

    Route::post('/inventory-movements/filters', [
        'as' => 'admin.inventory_movement.filters',
        'uses' => 'InventoryMovementController@filters',
        'middleware' => 'can:admin.inventory_movement.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.product.show',
        'uses' => 'ProductController@show',
        'middleware' => 'can:admin.product.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/block', [
        'as' => 'admin.product.block',
        'uses' => 'ProductController@block',
        'middleware' => 'can:admin.product.block',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/unblock', [
        'as' => 'admin.product.unblock',
        'uses' => 'ProductController@unblock',
        'middleware' => 'can:admin.product.unblock',
    ])->where('id', '[0-9]+');
});
