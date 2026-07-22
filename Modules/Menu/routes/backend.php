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

Route::prefix('menu')->group(function() {
    Route::get('/', [
        'as' => 'admin.menu.index',
        'uses' => 'MenuController@index',
        'middleware' => 'can:admin.menu.index'
    ]);


    Route::post('/postIndex', [
        'as' => 'admin.menu.postIndex',
        'uses' => 'MenuController@postIndex',
        'middleware' => 'can:admin.menu.filters'
    ]);

    Route::post('/filters', [
        'as' => 'admin.menu.filters',
        'uses' => 'MenuController@filters',
        'middleware' => 'can:admin.menu.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.menu.create',
        'uses' => 'MenuController@create',
        'middleware' => 'can:admin.menu.create'
    ]);

    Route::post('/', [
        'as' => 'admin.menu.store',
        'uses' => 'MenuController@store',
        'middleware' => 'can:admin.menu.create'
    ]); 

    Route::get('/edit/{id}', [
        'as' => 'admin.menu.edit',
        'uses' => 'MenuController@edit',
        'middleware' => 'can:admin.menu.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.menu.update',
        'uses' => 'MenuController@update',
        'middleware' => 'can:admin.menu.edit'
    ]);

    Route::post('/update_status', [
        'as' => 'admin.menu.update_status',
        'uses' => 'MenuController@updateStatus',
        'middleware' => 'can:admin.menu.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.menu.delete',
        'uses' => 'MenuController@delete',
        'middleware' => 'can:admin.menu.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.menu.mass_delete',
        'uses' => 'MenuController@massDelete',
        'middleware' => 'can:admin.menu.mass_delete'
    ]);
});
