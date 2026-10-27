<?php

/*
|--------------------------------------------------------------------------
| Supplier account routes
|--------------------------------------------------------------------------
|
| Loaded under /{seller.panel_prefix} with the "seller." name prefix and the "web"
| middleware only: sign-in, registration (mobile OTP) and password reset for guests, then
| the onboarding wizard and status page, which need a login but not an approved seller.
| The panel itself lives in seller.php.
|
*/

Route::get('/login', [
    'as' => 'login',
    'uses' => 'AuthController@showLogin',
]);
Route::post('/login', [
    'as' => 'login.attempt',
    'uses' => 'AuthController@login',
    'middleware' => 'throttle:10,1',
]);
Route::post('/login/otp', [
    'as' => 'login.otp',
    'uses' => 'AuthController@sendLoginOtp',
    'middleware' => 'throttle:otp',
]);

Route::get('/register', [
    'as' => 'register',
    'uses' => 'RegisterController@show',
]);
Route::post('/register', [
    'as' => 'register.store',
    'uses' => 'RegisterController@start',
    'middleware' => 'throttle:otp',
]);
Route::get('/register/verify', [
    'as' => 'register.verify',
    'uses' => 'RegisterController@showVerify',
]);
Route::post('/register/otp', [
    'as' => 'register.otp',
    'uses' => 'RegisterController@verify',
    'middleware' => 'throttle:10,1',
]);
Route::post('/register/resend', [
    'as' => 'register.resend',
    'uses' => 'RegisterController@resend',
    'middleware' => 'throttle:otp',
]);

Route::get('/password/forgot', [
    'as' => 'password.request',
    'uses' => 'ForgotPasswordController@showLinkRequestForm',
]);
Route::post('/password/forgot', [
    'as' => 'password.email',
    'uses' => 'ForgotPasswordController@sendResetLinkEmail',
    'middleware' => 'throttle:5,1',
]);
Route::get('/password/reset/{token}', [
    'as' => 'password.reset',
    'uses' => 'ResetPasswordController@showResetForm',
]);
Route::post('/password/reset', [
    'as' => 'password.update',
    'uses' => 'ResetPasswordController@reset',
    'middleware' => 'throttle:5,1',
]);

Route::middleware('auth:seller')->group(function () {
    Route::post('/logout', [
        'as' => 'logout',
        'uses' => 'AuthController@logout',
    ]);

    Route::get('/onboarding/status', [
        'as' => 'onboarding.status',
        'uses' => 'OnboardingController@status',
    ]);
    Route::post('/onboarding/restart', [
        'as' => 'onboarding.restart',
        'uses' => 'OnboardingController@restart',
    ]);
    Route::get('/onboarding/bank-status', [
        'as' => 'onboarding.bank_status',
        'uses' => 'OnboardingController@bankStatus',
    ]);
    Route::get('/ifsc/{ifsc}', [
        'as' => 'ifsc.lookup',
        'uses' => 'OnboardingController@ifsc',
        'middleware' => 'throttle:30,1',
    ])->where('ifsc', '[A-Za-z0-9]{11}');
    Route::get('/onboarding/{step?}', [
        'as' => 'onboarding.show',
        'uses' => 'OnboardingController@show',
    ])->where('step', '[1-5]');
    Route::post('/onboarding/{step}', [
        'as' => 'onboarding.save',
        'uses' => 'OnboardingController@save',
    ])->where('step', '[1-5]');

    // Own KYC files, at every stage (the wizard links to them); SellerOwnedPolicy guards tenancy.
    Route::get('/kyc/{document}', [
        'as' => 'kyc.download',
        'uses' => 'KycDocumentController@show',
    ])->where('document', '[0-9]+');
});
