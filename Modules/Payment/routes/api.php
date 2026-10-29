<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Payment API (M11)
|--------------------------------------------------------------------------
|
| api/{version}/orders/{orderNo}/... for the native Razorpay SDK and COD OTP. Customers only
| (Passport token with the "customer" scope); another customer's order is a 404.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/orders/{orderNo}')
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::post('/payment', [
                    'as' => 'payment_api.initiate',
                    'uses' => $upperCaseVersion.'\PaymentController@initiate',
                    'middleware' => 'throttle:api-checkout',
                ]);
                Route::post('/payment/verify', [
                    'as' => 'payment_api.verify',
                    'uses' => $upperCaseVersion.'\PaymentController@verify',
                ]);
                Route::post('/cod-otp/verify', [
                    'as' => 'payment_api.cod_otp.verify',
                    'uses' => $upperCaseVersion.'\PaymentController@verifyCodOtp',
                ]);
                Route::post('/cod-otp/resend', [
                    'as' => 'payment_api.cod_otp.resend',
                    'uses' => $upperCaseVersion.'\PaymentController@resendCodOtp',
                    'middleware' => 'throttle:5,1',
                ]);
            });
    }
}
