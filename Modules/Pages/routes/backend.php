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

Route::get('/', function() {
    return redirect('backend/login');
});

Route::prefix('page')->group(function() {
    Route::get('/', [
        'as' => 'admin.page.index',
        'uses' => 'PagesController@index',
        'middleware' => 'can:admin.page.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.page.filters',
        'uses' => 'PagesController@filters',
        'middleware' => 'can:admin.page.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.page.create',
        'uses' => 'PagesController@create',
        'middleware' => 'can:admin.page.create'
    ]);

    Route::post('/', [
        'as' => 'admin.page.store',
        'uses' => 'PagesController@store',
        'middleware' => 'can:admin.page.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.page.edit',
        'uses' => 'PagesController@edit',
        'middleware' => 'can:admin.page.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.page.update',
        'uses' => 'PagesController@update',
        'middleware' => 'can:admin.page.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.page.delete',
        'uses' => 'PagesController@delete',
        'middleware' => 'can:admin.page.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.page.mass_delete',
        'uses' => 'PagesController@massDelete',
        'middleware' => 'can:admin.page.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.page.update_status',
        'uses' => 'PagesController@updateStatus',
        'middleware' => 'can:admin.page.edit'
    ]);
});
