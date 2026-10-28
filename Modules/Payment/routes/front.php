<?php

/*
|--------------------------------------------------------------------------
| Payment pages (M11)
|--------------------------------------------------------------------------
|
| /checkout/pay/{orderNo}... and /checkout/cod-otp/{orderNo}... for the signed-in customer
| who owns the order (404 for anyone else). Orders come from the Order module (M12)
| through PayableOrderResolver.
|
*/

Route::middleware('auth:customer')->group(function () {
    Route::get('/checkout/pay/{orderNo}', [
        'as' => 'payment.pay',
        'uses' => 'CheckoutPaymentController@show',
    ]);

    Route::post('/checkout/pay/{orderNo}/callback', [
        'as' => 'payment.callback',
        'uses' => 'CheckoutPaymentController@callback',
        'middleware' => 'throttle:20,1',
    ]);

    Route::post('/checkout/pay/{orderNo}/retry', [
        'as' => 'payment.retry',
        'uses' => 'CheckoutPaymentController@retry',
        'middleware' => 'throttle:20,1',
    ]);

    Route::post('/checkout/pay/{orderNo}/cod', [
        'as' => 'payment.switch_cod',
        'uses' => 'CheckoutPaymentController@switchToCod',
        'middleware' => 'throttle:10,1',
    ]);

    Route::get('/checkout/pay/{orderNo}/failed', [
        'as' => 'payment.failed',
        'uses' => 'CheckoutPaymentController@failed',
    ]);

    /*
    | Local work with the fake gateway only: plays the buyer paying (404 otherwise).
    */
    Route::post('/checkout/pay/{orderNo}/simulate', [
        'as' => 'payment.simulate',
        'uses' => 'CheckoutPaymentController@simulate',
        'middleware' => 'throttle:20,1',
    ]);

    Route::get('/checkout/cod-otp/{orderNo}', [
        'as' => 'payment.cod_otp.show',
        'uses' => 'CodOtpController@show',
    ]);

    Route::post('/checkout/cod-otp/{orderNo}', [
        'as' => 'payment.cod_otp.verify',
        'uses' => 'CodOtpController@verify',
        'middleware' => 'throttle:10,1',
    ]);

    Route::post('/checkout/cod-otp/{orderNo}/resend', [
        'as' => 'payment.cod_otp.resend',
        'uses' => 'CodOtpController@resend',
        'middleware' => 'throttle:5,1',
    ]);
});
