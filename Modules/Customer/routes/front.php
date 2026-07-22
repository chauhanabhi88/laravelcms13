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

Route::prefix('customer')->group(function() {
    Route::get('/login', [
        'as' => 'customer.login',
        'uses' => 'LoginController@index',
        //'middleware' => 'auth.guest:customer'
    ]);

    Route::post('/login', [
        'as' => 'customer.loginpost',
        'uses' => 'LoginController@postLogin',
    ]);

    Route::get('/verify/{id}', [
        'as' => 'customer.email.verification',
        'uses' => 'RegisterController@emailVerification',
    ]);

    Route::get('/signup',[
        'as' => 'customer.signup',
        'uses' => 'RegisterController@index',
    ]);

    Route::post('/signup', [
        'as' => 'customer.signuppost',
        'uses' => 'RegisterController@postRegister',
    ]);
    Route::get('/logout', [
        'as' => 'customer.logout',
        'uses' => 'LoginController@logout',
    ]);

    /**
     * Reset Password routes
     */
    Route::get('/forgot', [
        'as'   => 'customer.forgot',
        'uses' => 'ForgotPasswordController@showLinkRequestForm'
    ]);

    Route::post('/forgot', [
        'as'   => 'customer.forgot.post',
        'uses' => 'ForgotPasswordController@sendResetLinkEmail'
    ]);

    Route::get('/reset/{token}/{email}', [
        'as'   => 'customer.reset',
        'uses' => 'ResetPasswordController@showResetForm'
    ]);

    Route::post('/reset', [
        'as'   => 'customer.reset.post',
        'uses' => 'ResetPasswordController@reset'
    ]);

    /**
     * account and profile routes
     */
    Route::get('/myaccount', [
        'as' => 'customer.myaccount',
        'uses' => 'CustomerController@myaccount',
        'middleware' => 'auth:customer'
    ]);
    Route::get('/edit',[
        'as' => 'customer.profile.edit',
        'uses' => 'CustomerController@profile',
        'middleware' => 'auth:customer'
    ]);
     Route::put('/{id}',[
        'as' => 'customer.profile.update',
        'uses' => 'CustomerController@update',
        'middleware' => 'auth:customer'
    ]);

    Route::post('/update-profile', [
        'as' => 'customer.update',
        'uses' => 'CustomerController@update',
         'middleware' => 'auth:customer'
    ]);
    Route::get('/change-password', [
        'as' => 'customer.change-password',
        'uses' => 'CustomerController@changePassword',
        'middleware' => 'auth:customer'
    ]);
    Route::post('/update-password',[
        'as' => 'customer.update-password',
        'uses' => 'CustomerController@updatePassword',
        //'middleware' => 'auth:customer'
    ]);


    Route::post('/customer_log', [
        'as' => 'customer.customer_online_offline_log',
        'uses' => 'CustomerController@checkOnlineOfflineLog',
    ]);
});
