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

Route::prefix('directory')->group(function () {
    Route::get('/', [
        'as' => 'admin.directory.index',
        'uses' => 'IndexController@index',
        'middleware' => 'can:admin.directory.index',
    ]);

    Route::post('/', [
        'as' => 'admin.directory.save',
        'uses' => 'IndexController@save',
        'middleware' => 'can:admin.directory.create',
    ]);
});

Route::prefix('country')->group(function () {
    Route::get('/', [
        'as' => 'admin.country.index',
        'uses' => 'CountryController@index',
        'middleware' => 'can:admin.country.index',
    ]);

    Route::post('/save', [
        'as' => 'admin.country.save',
        'uses' => 'CountryController@save',
        'middleware' => 'can:admin.country.save',
    ]);

    Route::post('/get_cities', [
        'as' => 'admin.country.get_cities',
        'uses' => 'CountryController@getCities',
        'middleware' => 'can:admin.country.get_cities',
    ]);

    Route::post('/getStates', [
        'as' => 'admin.country.get_states',
        'uses' => 'CountryController@getStates',
        'middleware' => 'can:admin.country.index',
    ]);

    Route::get('/export', [
        'as' => 'admin.country.export',
        'uses' => 'CountryController@export',
        'middleware' => 'can:admin.country.export',
    ]);

    Route::post('/import', [
        'as' => 'admin.country.import',
        'uses' => 'CountryController@import',
        'middleware' => 'can:admin.country.import',
    ]);

    Route::get('/importSample', [
        'as' => 'admin.country.importSample',
        'uses' => 'CountryController@importSample',
        'middleware' => 'can:admin.country.import',
    ]);
});

Route::prefix('city')->group(function () {
    Route::get('/export', [
        'as' => 'admin.city.export',
        'uses' => 'CityController@export',
        'middleware' => 'can:admin.city.export',
    ]);
    Route::post('/import', [
        'as' => 'admin.city.import',
        'uses' => 'CityController@import',
        'middleware' => 'can:admin.city.import',
    ]);

    Route::get('/importSample', [
        'as' => 'admin.city.importSample',
        'uses' => 'CityController@importSample',
        'middleware' => 'can:admin.city.import',
    ]);
});

Route::prefix('state')->group(function () {
    Route::get('/export', [
        'as' => 'admin.state.export',
        'uses' => 'StateController@export',
        'middleware' => 'can:admin.state.export',
    ]);
    Route::post('/import', [
        'as' => 'admin.state.import',
        'uses' => 'StateController@import',
        'middleware' => 'can:admin.state.import',
    ]);

    Route::get('/importSample', [
        'as' => 'admin.state.importSample',
        'uses' => 'StateController@importSample',
        'middleware' => 'can:admin.state.import',
    ]);

    Route::post('/getStateCities', [
        'as' => 'admin.state.get_cities',
        'uses' => 'StateController@getStateCities',
        'middleware' => 'can:admin.country.index',
    ]);
});
