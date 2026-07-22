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

Route::prefix('theme')->group(function() {
    Route::get('/', [
        'as' => 'admin.theme.index',
        'uses' => 'ThemeController@setTheme',
        'middleware' => 'can:admin.theme.index'
    ]);
    Route::post('/store', [
        'as' => 'admin.theme.store',
        'uses' => 'ThemeController@store',
        'middleware' => 'can:admin.theme.store'
    ]);

    Route::get('/reset', [
        'as' => 'admin.theme.reset',
        'uses' => 'ThemeController@reset',
        'middleware' => 'can:admin.theme.reset'
    ]);
});
