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
Route::get('/', [
    'as' => 'homepage',
    'uses' => 'PageController@index'
]);

Route::get('/wrong_lang_home', [
    'as' => 'wrong_lang_home',
    'uses' => 'PageController@wrongLangHome'
]);

Route::any('/{slug}', [
	'as' => 'page',
    'uses' => 'PageController@page'
]);