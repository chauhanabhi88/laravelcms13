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

Route::get('/contact', [
    'as' => 'contact.index',
    'uses' => 'ContactController@index',
]);
Route::post('/contact', [
    'as' => 'contact.store',
    'uses' => 'ContactController@store',
    'middleware' => 'throttle:5,1',
]);

/* Old URL - keep links that were already shared working. */
Route::get('/contact/info', fn () => redirect(frontRoute('contact.index'), 301));
