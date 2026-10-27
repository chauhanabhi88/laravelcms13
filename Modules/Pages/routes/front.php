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

// "/" (route "homepage") belongs to the Storefront module since M06; its home sections
// include the CMS hero banners, intro block and featured articles that PageController@index showed.

Route::get('/wrong_lang_home', [
    'as' => 'wrong_lang_home',
    'uses' => 'PageController@wrongLangHome',
]);

Route::any('/{slug}', [
    'as' => 'page',
    'uses' => 'PageController@page',
]);
