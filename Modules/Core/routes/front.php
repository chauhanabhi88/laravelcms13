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

if (config('core.translation')) {
    Route::get('/', function () {
        return redirect(app()->getLocale());
    });
}

/*
| Content-Security-Policy violation reports (plan M21 S2), CSRF-exempt in bootstrap/app.php.
*/
Route::post('/csp-report', [
    'as' => 'core.csp_report',
    'uses' => 'CspReportController@store',
    'middleware' => 'throttle:60,1',
]);

Route::post('/set-currency', [
    'as' => 'front.setcurrency',
    'uses' => 'FrontendController@setCurrency',
]);
