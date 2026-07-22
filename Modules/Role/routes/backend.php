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

Route::prefix('role')->group(function() {
    Route::get('/', [
        'as' => 'admin.role.index',
        'uses' => 'RoleController@index',
        'middleware' => 'can:admin.role.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.role.filters',
        'uses' => 'RoleController@filters',
        'middleware' => 'can:admin.role.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.role.create',
        'uses' => 'RoleController@create',
        'middleware' => 'can:admin.role.create'
    ]);

    Route::post('/', [
        'as' => 'admin.role.store',
        'uses' => 'RoleController@store',
        'middleware' => 'can:admin.role.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.role.edit',
        'uses' => 'RoleController@edit',
        'middleware' => 'can:admin.role.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.role.update',
        'uses' => 'RoleController@update',
        'middleware' => 'can:admin.role.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.role.delete',
        'uses' => 'RoleController@delete',
        'middleware' => 'can:admin.role.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.role.mass_delete',
        'uses' => 'RoleController@massDelete',
        'middleware' => 'can:admin.role.mass_delete'
    ]);
});