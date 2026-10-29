<?php

/*
|--------------------------------------------------------------------------
| Supplier payments (M17)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/payments..., names "seller.*", middleware
| web + auth:seller + seller.active. Finance staff and owners only. Controllers read route
| parameters with $request->route() (the optional {locale} prefix shifts positional ones).
|
*/

Route::middleware('seller.can:finance')->prefix('payments')->group(function () {
    Route::get('/', [
        'as' => 'payments.overview',
        'uses' => 'PaymentsController@overview',
    ]);
    Route::get('/statement', [
        'as' => 'payments.statement',
        'uses' => 'StatementController@index',
    ]);
    Route::get('/statement/export', [
        'as' => 'payments.statement.export',
        'uses' => 'StatementController@export',
    ]);
    Route::get('/payouts', [
        'as' => 'payments.payouts',
        'uses' => 'PaymentsController@payouts',
    ]);
    Route::get('/payouts/{payoutNo}', [
        'as' => 'payments.payouts.show',
        'uses' => 'PaymentsController@showPayout',
    ])->where('payoutNo', 'PAY-[0-9A-Z-]+');
    Route::get('/orders/{subOrderNo}', [
        'as' => 'payments.order_settlement',
        'uses' => 'PaymentsController@orderSettlement',
    ])->where('subOrderNo', '[0-9A-Z-]+');
    Route::get('/invoices', [
        'as' => 'payments.invoices',
        'uses' => 'InvoiceController@index',
    ]);
    Route::get('/invoices/{document}/download', [
        'as' => 'payments.invoices.download',
        'uses' => 'InvoiceController@download',
    ])->where('document', '[0-9]+');
});
