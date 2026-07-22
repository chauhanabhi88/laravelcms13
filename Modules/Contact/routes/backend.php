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

Route::prefix('contact')->group(function() {
    Route::get('/', [
        'as' => 'admin.contact.index',
        'uses' => 'ContactController@index',
        'middleware' => 'can:admin.contact.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.contact.filters',
        'uses' => 'ContactController@filters',
        'middleware' => 'can:admin.contact.filters'
    ]);

    // Route::get('/create', [
    //     'as' => 'admin.contact.create',
    //     'uses' => 'ContactController@create',
    //     'middleware' => 'can:admin.contact.create'
    // ]);

    Route::post('/export', [
        'as' => 'admin.contact.export',
        'uses' => 'ContactController@export',
        'middleware' => 'can:admin.contact.export'
    ]);

    Route::post('/', [
        'as' => 'admin.contact.store',
        'uses' => 'ContactController@store',
        'middleware' => 'can:admin.contact.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.contact.edit',
        'uses' => 'ContactController@edit',
        'middleware' => 'can:admin.contact.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.contact.update',
        'uses' => 'ContactController@update',
        'middleware' => 'can:admin.contact.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.contact.delete',
        'uses' => 'ContactController@delete',
        'middleware' => 'can:admin.contact.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.contact.mass_delete',
        'uses' => 'ContactController@massDelete',
        'middleware' => 'can:admin.contact.mass_delete'
    ]);

     Route::get('/view/{id}', [
        'as' => 'admin.contact.view',
        'uses' => 'ContactController@view',
        'middleware' => 'can:admin.contact.view'
    ]);

});
