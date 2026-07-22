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

Route::get('/login', [
    'as'   => 'backend_login',
    'uses' => 'AuthController@index',
    'middleware' => 'auth.guest'
]);
Route::post('/loginPost', [
    'as'   => 'backend_post_login',
    'uses' => 'AuthController@postLogin',
    'middleware' => 'throttle:adminauth'
]);

Route::get('/logout', [
    'as'   => 'backend_logout',
    'uses' => 'AuthController@logout'
]);




/**
 * Reset Password routes
 */
Route::get('/reset', [
    'as'   => 'reset',
    'uses' => 'ForgotPasswordController@showLinkRequestForm'
]);

Route::post('/reset', [
    'as'   => 'reset.post',
    'uses' => 'ForgotPasswordController@sendResetLinkEmail'
]);

Route::get('/admin_reset/{token}/{email}', [
    'as'   => 'password.reset',
    'uses' => 'ResetPasswordController@showResetForm'
]);

Route::post('/resetPassword', [
    'as'   => 'reset.complete.post',
    'uses' => 'ResetPasswordController@reset'
]);
