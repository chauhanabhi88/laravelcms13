<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('column')->group(function () {
    Route::get('/', [
        'as' => 'admin.column.index',
        'uses' => 'ColumnController@index',
        'middleware' => 'can:admin.column.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.column.filters',
        'uses' => 'ColumnController@filters',
        'middleware' => 'can:admin.column.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.column.create',
        'uses' => 'ColumnController@create',
        'middleware' => 'can:admin.column.create'
    ]);

    Route::post('/', [
        'as' => 'admin.column.store',
        'uses' => 'ColumnController@store',
        'middleware' => 'can:admin.column.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.column.edit',
        'uses' => 'ColumnController@edit',
        'middleware' => 'can:admin.column.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.column.update',
        'uses' => 'ColumnController@update',
        'middleware' => 'can:admin.column.edit'
    ]);

    Route::post('/update_status', [
        'as' => 'admin.column.update_status',
        'uses' => 'ColumnController@updateStatus',
        'middleware' => 'can:admin.column.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.column.delete',
        'uses' => 'ColumnController@delete',
        'middleware' => 'can:admin.column.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.column.mass_delete',
        'uses' => 'ColumnController@massDelete',
        'middleware' => 'can:admin.column.mass_delete'
    ]);

    Route::post('/save', [
        'as' => 'admin.column.save',
        'uses' => 'ColumnController@saveDefaultColumns',
        // 'middleware' => 'can:admin.column.save'
    ]);
});
