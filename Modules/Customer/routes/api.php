<?php

use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

// Route::middleware(['auth:api', 'scope:customer'])->prefix('customer')->group(function() {
// Route::middleware(['auth:customer', EnsureClientIsResourceOwner::using('customer')])->prefix('customer')->group(function() {
// Route::middleware([EnsureClientIsResourceOwner::using('guest')])->prefix('customer')->group(function() {
//     Route::get('/', [
//         'as' => 'customer_api.index',
//         'uses' => 'IndexController@index'
//     ]);
// });

// Route::middleware([EnsureClientIsResourceOwner::using('guest')])->prefix('customer')->group(function() {
//     Route::get('/', [
//         'as' => 'customer_api.index',
//         'uses' => 'IndexController@index'
//     ]);
// });

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::prefix($version.'/customer')->group(function () use ($upperCaseVersion) {
            // Route::post('/login', [
            //     'as' => 'customer_api.login',
            //     'uses' => $upperCaseVersion.'\IndexController@login'
            // ]);
            // Route::post('/register', [
            //     'as' => 'customer_api.register',
            //     'uses' => $upperCaseVersion.'\IndexController@register'
            // ]);

            // Route::post('/sendOtp', [
            //     'as' => 'customer_api.sendotp',
            //     'uses' => $upperCaseVersion.'\IndexController@sendOtp',
            // ]);

            // Route::post('/validateOtp', [
            //     'as' => 'customer_api.validateotp',
            //     'uses' => $upperCaseVersion.'\IndexController@validateOtp',
            // ]);

            Route::get('/', [
                'as' => 'customer_api.index',
                'uses' => $upperCaseVersion.'\IndexController@index',
            ]);
        });

        // Route::middleware(['auth:api', EnsureClientIsResourceOwner::using('customer')])->prefix($version."/customer")->group(function() use($upperCaseVersion) {
        //     Route::get('/profile', [
        //         'as' => 'customer_api.profile',
        //         'uses' => $upperCaseVersion.'\CustomerController@profile'
        //     ]);
        // });

        // Route::middleware([EnsureClientIsResourceOwner::using('guest')])->prefix($version .'/customers')->group(function () use ($upperCaseVersion) {
        // Route::middleware('client:guest')->prefix('/customers')->group(function () {
        Route::prefix($version.'/customers')->group(function () use ($upperCaseVersion) {
            Route::post('/login', [
                'as' => 'customer_api.login',
                'uses' => $upperCaseVersion.'\CustomerController@login',
                'middleware' => 'throttle:20,1',
            ]);

            Route::post('/signup', [
                'as' => 'customer_api.signup',
                'uses' => $upperCaseVersion.'\CustomerController@signup',
                'middleware' => 'throttle:20,1',
            ]);

            Route::get('/', [
                'as' => 'customer_api.list',
                'uses' => $upperCaseVersion.'\CustomerController@index',
                'middleware' => ['auth:users', 'can:admin.customer.index'],
            ]);

            Route::post('/filter', [
                'as' => 'customer_api.filters',
                'uses' => $upperCaseVersion.'\CustomerController@filters',
                'middleware' => ['auth:users', 'can:admin.customer.filters'],
            ]);

            Route::get('/edit/{id}', [
                'as' => 'customer_api.edit',
                'uses' => $upperCaseVersion.'\CustomerController@edit',
                'middleware' => ['auth:users', 'can:admin.customer.edit'],
            ]);

            Route::post('/update/{id}', [
                'as' => 'customer_api.update',
                'uses' => $upperCaseVersion.'\CustomerController@update',
                'middleware' => ['auth:users', 'can:admin.customer.edit'],
            ]);

            Route::post('/address/save', [
                'as' => 'customer_api.address.save',
                'uses' => $upperCaseVersion.'\CustomerController@saveAddress',
                'middleware' => ['auth:users', 'can:admin.customer.address'],
            ]);

            Route::delete('/address/delete/{id}', [
                'as' => 'customer_api.address.delete',
                'uses' => $upperCaseVersion.'\CustomerController@deleteAddress',
                'middleware' => ['auth:users', 'can:admin.address.delete'],
            ]);

            Route::delete('/delete/{id}', [
                'as' => 'customer_api.delete',
                'uses' => $upperCaseVersion.'\CustomerController@destroy',
                'middleware' => ['auth:users', 'can:admin.customer.delete'],
            ]);

            Route::post('/store', [
                'as' => 'customer_api.address.store',
                'uses' => $upperCaseVersion.'\CustomerController@store',
                'middleware' => ['auth:users', 'can:admin.customer.create'],
            ]);

            Route::post('/update_status', [
                'as' => 'customer_api.udpate_status',
                'uses' => $upperCaseVersion.'\CustomerController@updateStatus',
                'middleware' => ['auth:users', 'can:admin.customer.edit'],
            ]);

            Route::post('/mass_delete', [
                'as' => 'customer_api.mass_delete',
                'uses' => $upperCaseVersion.'\CustomerController@massDelete',
                'middleware' => ['auth:users', 'can:admin.customer.mass_delete'],
            ]);
        });

    }
}
