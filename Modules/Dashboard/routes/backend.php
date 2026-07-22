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

Route::prefix('dashboard')->group(function() {
    Route::get('/', [
        'as' => 'admin.dashboard.index',
        'uses' => 'DashboardController@index',
        'middleware' => 'can:admin.dashboard.index'
    ]);

    Route::get('/clearCache', [
        'as' => 'admin.dashboard.clear_all_cache',
        'uses' => 'DashboardController@clearCache',
        'middleware' => 'can:admin.dashboard.clear_all_cache'
    ]);
});
