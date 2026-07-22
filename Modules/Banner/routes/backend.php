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

Route::prefix('banner')->group(function () {
    Route::get('/', [
        'as' => 'admin.banner.index',
        'uses' => 'BannerController@index',
        'middleware' => 'can:admin.banner.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.banner.filters',
        'uses' => 'BannerController@filters',
        'middleware' => 'can:admin.banner.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.banner.create',
        'uses' => 'BannerController@create',
        'middleware' => 'can:admin.banner.create'
    ]);

    Route::post('/', [
        'as' => 'admin.banner.store',
        'uses' => 'BannerController@store',
        'middleware' => 'can:admin.banner.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.banner.edit',
        'uses' => 'BannerController@edit',
        'middleware' => 'can:admin.banner.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.banner.update',
        'uses' => 'BannerController@update',
        'middleware' => 'can:admin.banner.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.banner.delete',
        'uses' => 'BannerController@delete',
        'middleware' => 'can:admin.banner.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.banner.mass_delete',
        'uses' => 'BannerController@massDelete',
        'middleware' => 'can:admin.banner.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.banner.update_status',
        'uses' => 'BannerController@updateStatus',
        'middleware' => 'can:admin.banner.edit'
    ]);
});


Route::prefix('bannergroup')->group(function () {

    Route::get('/', [
        'as' => 'admin.bannergroup.index',
        'uses' => 'BannerGroupController@index',
        'middleware' => 'can:admin.bannergroup.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.bannergroup.filters',
        'uses' => 'BannerGroupController@filters',
        'middleware' => 'can:admin.bannergroup.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.bannergroup.create',
        'uses' => 'BannerGroupController@create',
        'middleware' => 'can:admin.bannergroup.create'
    ]);

    Route::post('/', [
        'as' => 'admin.bannergroup.store',
        'uses' => 'BannerGroupController@store',
        'middleware' => 'can:admin.bannergroup.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.bannergroup.edit',
        'uses' => 'BannerGroupController@edit',
        'middleware' => 'can:admin.bannergroup.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.bannergroup.update',
        'uses' => 'BannerGroupController@update',
        'middleware' => 'can:admin.bannergroup.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.bannergroup.delete',
        'uses' => 'BannerGroupController@delete',
        'middleware' => 'can:admin.bannergroup.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.bannergroup.mass_delete',
        'uses' => 'BannerGroupController@massDelete',
        'middleware' => 'can:admin.bannergroup.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.bannergroup.update_status',
        'uses' => 'BannerGroupController@updateStatus',
        'middleware' => 'can:admin.bannergroup.edit'
    ]);
});
