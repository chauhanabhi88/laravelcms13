<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('block')->group(function() {
    Route::get('/', [
        'as' => 'admin.block.index',
        'uses' => 'BlockController@index',
        'middleware' => 'can:admin.block.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.block.filters',
        'uses' => 'BlockController@filters',
        'middleware' => 'can:admin.block.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.block.create',
        'uses' => 'BlockController@create',
        'middleware' => 'can:admin.block.create'
    ]);

    Route::post('/', [
        'as' => 'admin.block.store',
        'uses' => 'BlockController@store',
        'middleware' => 'can:admin.block.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.block.edit',
        'uses' => 'BlockController@edit',
        'middleware' => 'can:admin.block.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.block.update',
        'uses' => 'BlockController@update',
        'middleware' => 'can:admin.block.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.block.delete',
        'uses' => 'BlockController@delete',
        'middleware' => 'can:admin.block.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.block.mass_delete',
        'uses' => 'BlockController@massDelete',
        'middleware' => 'can:admin.block.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.block.update_status',
        'uses' => 'BlockController@updateStatus',
        'middleware' => 'can:admin.block.edit'
    ]);
});