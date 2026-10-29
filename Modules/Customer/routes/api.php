<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use Modules\Customer\Services\CustomerAuthService;

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
        Route::prefix($version.'/customer')->middleware('api.envelope')->group(function () use ($upperCaseVersion) {
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

            /*
             * M08: mobile OTP sign-in. otp/verify issues a personal access token with the
             * "customer" scope; everything below needs that token.
             */
            Route::post('/otp/send', [
                'as' => 'customer_api.otp.send',
                'uses' => $upperCaseVersion.'\AuthController@sendOtp',
                'middleware' => 'throttle:api-auth',
            ]);
            Route::post('/otp/verify', [
                'as' => 'customer_api.otp.verify',
                'uses' => $upperCaseVersion.'\AuthController@verifyOtp',
                'middleware' => 'throttle:api-auth-verify',
            ]);

            Route::middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api'])->group(function () use ($upperCaseVersion) {
                Route::post('/logout', [
                    'as' => 'customer_api.logout',
                    'uses' => $upperCaseVersion.'\AuthController@logout',
                ]);

                Route::get('/devices', [
                    'as' => 'customer_api.devices.index',
                    'uses' => $upperCaseVersion.'\DeviceController@index',
                ]);
                Route::delete('/devices/{device}', [
                    'as' => 'customer_api.devices.destroy',
                    'uses' => $upperCaseVersion.'\DeviceController@destroy',
                ])->where('device', '[A-Za-z0-9]+');

                Route::get('/profile', [
                    'as' => 'customer_api.profile.show',
                    'uses' => $upperCaseVersion.'\ProfileController@show',
                ]);
                Route::put('/profile', [
                    'as' => 'customer_api.profile.update',
                    'uses' => $upperCaseVersion.'\ProfileController@update',
                ]);

                Route::get('/addresses', [
                    'as' => 'customer_api.addresses.index',
                    'uses' => $upperCaseVersion.'\AddressController@index',
                ]);
                Route::post('/addresses', [
                    'as' => 'customer_api.addresses.store',
                    'uses' => $upperCaseVersion.'\AddressController@store',
                ]);
                Route::put('/addresses/{address}', [
                    'as' => 'customer_api.addresses.update',
                    'uses' => $upperCaseVersion.'\AddressController@update',
                ])->where('address', '[0-9]+');
                Route::delete('/addresses/{address}', [
                    'as' => 'customer_api.addresses.destroy',
                    'uses' => $upperCaseVersion.'\AddressController@destroy',
                ])->where('address', '[0-9]+');

                Route::get('/wishlist', [
                    'as' => 'customer_api.wishlist.index',
                    'uses' => $upperCaseVersion.'\WishlistController@index',
                ]);
                Route::post('/wishlist', [
                    'as' => 'customer_api.wishlist.store',
                    'uses' => $upperCaseVersion.'\WishlistController@store',
                ]);
                Route::delete('/wishlist/{product}', [
                    'as' => 'customer_api.wishlist.destroy',
                    'uses' => $upperCaseVersion.'\WishlistController@destroy',
                ])->where('product', '[0-9]+');
            });
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
