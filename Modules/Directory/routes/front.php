<?php

/*
|--------------------------------------------------------------------------
Frontend Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::prefix('country')->group(function() {

    Route::post('/get_cities', [
        'as' => 'country.get_cities',
        'uses' => 'CountryController@getCities',
    ]);

    Route::get('/get_countries_code', [
    	'as' => 'country.get_countries_code',
    	'uses' => 'CountryController@getCountriesCode'
    ]);
});