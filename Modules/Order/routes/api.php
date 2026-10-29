<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Checkout and orders API (M12)
|--------------------------------------------------------------------------
|
| api/{version}/checkout/summary|place and api/{version}/orders[/{orderNo}[/cancel]] for
| customers (Passport token with the "customer" scope). Payment and COD OTP endpoints under
| /orders/{orderNo}/... belong to the Payment module.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version)
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::post('/checkout/summary', [
                    'as' => 'order_api.checkout.summary',
                    'uses' => $upperCaseVersion.'\CheckoutController@summary',
                ]);
                Route::post('/checkout/place', [
                    'as' => 'order_api.checkout.place',
                    'uses' => $upperCaseVersion.'\CheckoutController@place',
                    'middleware' => 'throttle:api-checkout',
                ]);
                Route::get('/orders', [
                    'as' => 'order_api.orders.index',
                    'uses' => $upperCaseVersion.'\OrderController@index',
                ]);
                Route::get('/orders/{orderNo}', [
                    'as' => 'order_api.orders.show',
                    'uses' => $upperCaseVersion.'\OrderController@show',
                ]);
                Route::get('/orders/{orderNo}/invoice/{sellerOrder}', [
                    'as' => 'order_api.orders.invoice',
                    'uses' => $upperCaseVersion.'\InvoiceController@link',
                ])->where('sellerOrder', '[0-9]+');
                Route::post('/orders/{orderNo}/cancel', [
                    'as' => 'order_api.orders.cancel',
                    'uses' => $upperCaseVersion.'\OrderController@cancel',
                    'middleware' => 'throttle:10,1',
                ]);
            });

        // The PDF behind the signed link from orders/{orderNo}/invoice/{sellerOrder}: no token, the signature authorises.
        Route::prefix($version)
            ->middleware(['signed', 'throttle:api'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/orders/{orderNo}/invoice/{sellerOrder}/download', [
                    'as' => 'order_api.orders.invoice.download',
                    'uses' => $upperCaseVersion.'\InvoiceController@download',
                ])->where('sellerOrder', '[0-9]+');
            });
    }
}
