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

Route::prefix('/')->group(function() {
    Route::get('manifest.json', [
        'as' => 'manifest',
        'uses' => 'IndexController@manifestJson',
    ]);

    Route::get("offline/", [
        'as' => 'offline',
        'uses' => 'IndexController@offline',
    ]);
});