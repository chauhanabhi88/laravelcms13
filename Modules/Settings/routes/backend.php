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
Route::prefix('settings')->group(function() {
    // die;

    Route::get('/', [
        'as' => 'admin.settings.index',
        'uses' => 'SettingsController@index',
        'middleware' => 'can:admin.settings.index'
    ]);

    Route::get('moduleSettings', [
        'as' => 'admin.settings.getModuleSetting',
        'uses' => 'SettingsController@getModuleSetting',
        'middleware' => 'can:admin.settings.getModuleSetting'
    ]);
    Route::post('save', [
        'as' => 'admin.settings.save',
        'uses' => 'SettingsController@save',
        'middleware' => 'can:admin.settings.save'
    ]);
});
