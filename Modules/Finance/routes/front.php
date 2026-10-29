<?php

/*
|--------------------------------------------------------------------------
| Reseller earnings (M17)
|--------------------------------------------------------------------------
|
| Signed-in customers in reseller mode: the margin wallet (available, pending, paid) and
| withdrawals to UPI or a bank account (FR-FIN-007).
|
*/

Route::middleware('auth:customer')->prefix('customer/earnings')->group(function () {
    Route::get('/', [
        'as' => 'customer.earnings.index',
        'uses' => 'Account\ResellerEarningsController@index',
    ]);

    Route::post('/withdraw', [
        'as' => 'customer.earnings.withdraw',
        'uses' => 'Account\ResellerEarningsController@withdraw',
        'middleware' => 'throttle:5,1',
    ]);
});
