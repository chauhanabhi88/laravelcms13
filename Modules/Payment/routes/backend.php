<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Payments admin (FR-PAY-007/008): transactions, refunds, webhook log with replay and the
| settlement reconciliation report. Loaded with the admin prefix and the "backend" group.
|
*/

Route::prefix('payment')->group(function () {
    Route::get('/', [
        'as' => 'admin.payment.index',
        'uses' => 'PaymentController@index',
        'middleware' => 'can:admin.payment.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.payment.filters',
        'uses' => 'PaymentController@filters',
        'middleware' => 'can:admin.payment.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.payment.show',
        'uses' => 'PaymentController@show',
        'middleware' => 'can:admin.payment.show',
    ])->where('id', '[0-9]+');
});

Route::prefix('payment-refund')->group(function () {
    Route::get('/', [
        'as' => 'admin.payment_refund.index',
        'uses' => 'PaymentRefundController@index',
        'middleware' => 'can:admin.payment_refund.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.payment_refund.filters',
        'uses' => 'PaymentRefundController@filters',
        'middleware' => 'can:admin.payment_refund.filters',
    ]);

    Route::post('/{id}/mark-processed', [
        'as' => 'admin.payment_refund.mark_processed',
        'uses' => 'PaymentRefundController@markProcessed',
        'middleware' => 'can:admin.payment_refund.mark_processed',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/retry', [
        'as' => 'admin.payment_refund.retry',
        'uses' => 'PaymentRefundController@retry',
        'middleware' => 'can:admin.payment_refund.retry',
    ])->where('id', '[0-9]+');
});

Route::prefix('webhook-event')->group(function () {
    Route::get('/', [
        'as' => 'admin.webhook_event.index',
        'uses' => 'WebhookEventController@index',
        'middleware' => 'can:admin.webhook_event.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.webhook_event.filters',
        'uses' => 'WebhookEventController@filters',
        'middleware' => 'can:admin.webhook_event.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.webhook_event.show',
        'uses' => 'WebhookEventController@show',
        'middleware' => 'can:admin.webhook_event.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/replay', [
        'as' => 'admin.webhook_event.replay',
        'uses' => 'WebhookEventController@replay',
        'middleware' => 'can:admin.webhook_event.replay',
    ])->where('id', '[0-9]+');
});

Route::prefix('payment-recon')->group(function () {
    Route::get('/', [
        'as' => 'admin.payment_recon.index',
        'uses' => 'PaymentReconController@index',
        'middleware' => 'can:admin.payment_recon.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.payment_recon.filters',
        'uses' => 'PaymentReconController@filters',
        'middleware' => 'can:admin.payment_recon.filters',
    ]);

    Route::post('/{id}/resolve', [
        'as' => 'admin.payment_recon.resolve',
        'uses' => 'PaymentReconController@resolve',
        'middleware' => 'can:admin.payment_recon.resolve',
    ])->where('id', '[0-9]+');
});
