<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Read-only cart list for customer support. Loaded with the admin prefix and the "backend"
| middleware group.
|
*/

Route::prefix('cart')->group(function () {
    Route::get('/', [
        'as' => 'admin.cart.index',
        'uses' => 'CartController@index',
        'middleware' => 'can:admin.cart.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.cart.filters',
        'uses' => 'CartController@filters',
        'middleware' => 'can:admin.cart.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.cart.show',
        'uses' => 'CartController@show',
        'middleware' => 'can:admin.cart.show',
    ])->where('id', '[0-9]+');
});
