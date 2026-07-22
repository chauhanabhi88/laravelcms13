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

Route::prefix('attribute')->group(function () {
    Route::get('/', [
        'as' => 'admin.attribute.index',
        'uses' => 'AttributeController@index',
        'middleware' => 'can:admin.attribute.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.attribute.filters',
        'uses' => 'AttributeController@filters',
        'middleware' => 'can:admin.attribute.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.attribute.create',
        'uses' => 'AttributeController@create',
        'middleware' => 'can:admin.attribute.create'
    ]);

    Route::post('/', [
        'as' => 'admin.attribute.store',
        'uses' => 'AttributeController@store',
        'middleware' => 'can:admin.attribute.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.attribute.edit',
        'uses' => 'AttributeController@edit',
        'middleware' => 'can:admin.attribute.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.attribute.update',
        'uses' => 'AttributeController@update',
        'middleware' => 'can:admin.attribute.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.attribute.delete',
        'uses' => 'AttributeController@delete',
        'middleware' => 'can:admin.attribute.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.attribute.mass_delete',
        'uses' => 'AttributeController@massDelete',
        'middleware' => 'can:admin.attribute.mass_delete'
    ]);
});
