<?php

/*
|--------------------------------------------------------------------------
| Checkout and My Orders (M12)
|--------------------------------------------------------------------------
|
| Signed-in customers only (guests are sent to the OTP login). Loaded before the Pages
| module's /{slug} catch-all (lower module priority). "checkout.index" is the name the Cart
| module's Continue and Buy Now buttons look for. The payment pages under /checkout/pay and
| /checkout/cod-otp belong to the Payment module.
|
*/

Route::middleware('auth:customer')->group(function () {
    Route::get('/checkout', [
        'as' => 'checkout.index',
        'uses' => 'CheckoutController@index',
    ]);

    Route::get('/checkout/address', [
        'as' => 'checkout.address',
        'uses' => 'CheckoutController@address',
    ]);

    Route::post('/checkout/address', [
        'as' => 'checkout.address.save',
        'uses' => 'CheckoutController@saveAddress',
        'middleware' => 'throttle:30,1',
    ]);

    Route::post('/checkout/reseller', [
        'as' => 'checkout.reseller.save',
        'uses' => 'CheckoutController@saveReseller',
        'middleware' => 'throttle:30,1',
    ]);

    Route::get('/checkout/payment', [
        'as' => 'checkout.payment',
        'uses' => 'CheckoutController@payment',
    ]);

    Route::post('/checkout/payment', [
        'as' => 'checkout.payment.save',
        'uses' => 'CheckoutController@savePayment',
        'middleware' => 'throttle:30,1',
    ]);

    Route::get('/checkout/summary', [
        'as' => 'checkout.summary',
        'uses' => 'CheckoutController@summary',
    ]);

    Route::post('/checkout/place', [
        'as' => 'checkout.place',
        'uses' => 'CheckoutController@place',
        'middleware' => 'throttle:5,1',
    ]);

    Route::get('/checkout/success/{orderNo}', [
        'as' => 'checkout.success',
        'uses' => 'OrderSuccessController@show',
    ]);

    /*
    | My Orders, next to the other account pages under /customer (M15 adds tracking and returns).
    */
    Route::prefix('customer/orders')->group(function () {
        Route::get('/', [
            'as' => 'customer.orders.index',
            'uses' => 'Account\OrderController@index',
        ]);

        Route::get('/{orderNo}', [
            'as' => 'customer.orders.show',
            'uses' => 'Account\OrderController@show',
        ]);

        Route::post('/{orderNo}/cancel', [
            'as' => 'customer.orders.cancel',
            'uses' => 'Account\OrderController@cancel',
            'middleware' => 'throttle:10,1',
        ]);
    });
});
