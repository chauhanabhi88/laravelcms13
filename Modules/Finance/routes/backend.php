<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Admin finance console (FR-FIN-008, plan M17 §6). Loaded with the admin prefix and the
| "backend" group. Every route carries its can: permission from config/permissions.php.
|
*/

$crud = function (string $prefix, string $name, string $controller): void {
    Route::prefix($prefix)->group(function () use ($name, $controller) {
        Route::get('/', ['as' => "admin.{$name}.index", 'uses' => "{$controller}@index", 'middleware' => "can:admin.{$name}.index"]);
        Route::post('/filters', ['as' => "admin.{$name}.filters", 'uses' => "{$controller}@filters", 'middleware' => "can:admin.{$name}.filters"]);
        Route::get('/create', ['as' => "admin.{$name}.create", 'uses' => "{$controller}@create", 'middleware' => "can:admin.{$name}.create"]);
        Route::post('/', ['as' => "admin.{$name}.store", 'uses' => "{$controller}@store", 'middleware' => "can:admin.{$name}.create"]);
        Route::get('/{id}/edit', ['as' => "admin.{$name}.edit", 'uses' => "{$controller}@edit", 'middleware' => "can:admin.{$name}.edit"])->where('id', '[0-9]+');
        Route::post('/{id}', ['as' => "admin.{$name}.update", 'uses' => "{$controller}@update", 'middleware' => "can:admin.{$name}.edit"])->where('id', '[0-9]+');
        Route::post('/{id}/delete', ['as' => "admin.{$name}.delete", 'uses' => "{$controller}@delete", 'middleware' => "can:admin.{$name}.delete"])->where('id', '[0-9]+');
    });
};

Route::prefix('finance')->group(function () use ($crud) {
    $crud('commission-rule', 'commission_rule', 'CommissionRuleController');
    $crud('fee-rule', 'fee_rule', 'FeeRuleController');

    Route::prefix('ledger')->group(function () {
        Route::get('/', ['as' => 'admin.ledger.index', 'uses' => 'LedgerController@index', 'middleware' => 'can:admin.ledger.index']);
        Route::post('/filters', ['as' => 'admin.ledger.filters', 'uses' => 'LedgerController@filters', 'middleware' => 'can:admin.ledger.filters']);
        Route::get('/export', ['as' => 'admin.ledger.export', 'uses' => 'LedgerController@export', 'middleware' => 'can:admin.ledger.export']);
        Route::post('/adjust', ['as' => 'admin.ledger.adjust', 'uses' => 'LedgerController@adjust', 'middleware' => 'can:admin.ledger.adjust']);
        Route::post('/adjustments/{id}/approve', ['as' => 'admin.ledger.approve_adjustment', 'uses' => 'LedgerController@approveAdjustment', 'middleware' => 'can:admin.ledger.approve_adjustment'])->where('id', '[0-9]+');
        Route::post('/adjustments/{id}/reject', ['as' => 'admin.ledger.reject_adjustment', 'uses' => 'LedgerController@rejectAdjustment', 'middleware' => 'can:admin.ledger.approve_adjustment'])->where('id', '[0-9]+');
    });

    Route::prefix('payout-batch')->group(function () {
        Route::get('/', ['as' => 'admin.payout_batch.index', 'uses' => 'PayoutBatchController@index', 'middleware' => 'can:admin.payout_batch.index']);
        Route::post('/filters', ['as' => 'admin.payout_batch.filters', 'uses' => 'PayoutBatchController@filters', 'middleware' => 'can:admin.payout_batch.filters']);
        Route::post('/build', ['as' => 'admin.payout_batch.build', 'uses' => 'PayoutBatchController@build', 'middleware' => 'can:admin.payout_batch.build']);
        Route::get('/{id}', ['as' => 'admin.payout_batch.show', 'uses' => 'PayoutBatchController@show', 'middleware' => 'can:admin.payout_batch.show'])->where('id', '[0-9]+');
        Route::post('/{id}/approve', ['as' => 'admin.payout_batch.approve', 'uses' => 'PayoutBatchController@approve', 'middleware' => 'can:admin.payout_batch.approve'])->where('id', '[0-9]+');
        Route::post('/{id}/process', ['as' => 'admin.payout_batch.process', 'uses' => 'PayoutBatchController@process', 'middleware' => 'can:admin.payout_batch.process'])->where('id', '[0-9]+');
        Route::get('/{id}/bank-file', ['as' => 'admin.payout_batch.export_bank_file', 'uses' => 'PayoutBatchController@exportBankFile', 'middleware' => 'can:admin.payout_batch.export_bank_file'])->where('id', '[0-9]+');
        Route::post('/{id}/utr', ['as' => 'admin.payout_batch.upload_utr', 'uses' => 'PayoutBatchController@uploadUtr', 'middleware' => 'can:admin.payout_batch.upload_utr'])->where('id', '[0-9]+');
    });

    Route::prefix('seller-payout')->group(function () {
        Route::post('/{id}/retry', ['as' => 'admin.seller_payout.retry', 'uses' => 'SellerPayoutController@retry', 'middleware' => 'can:admin.seller_payout.retry'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-paid', ['as' => 'admin.seller_payout.mark_paid', 'uses' => 'SellerPayoutController@markPaid', 'middleware' => 'can:admin.seller_payout.mark_paid'])->where('id', '[0-9]+');
    });

    Route::prefix('payout-hold')->group(function () {
        Route::get('/', ['as' => 'admin.payout_hold.index', 'uses' => 'PayoutHoldController@index', 'middleware' => 'can:admin.payout_hold.index']);
        Route::post('/filters', ['as' => 'admin.payout_hold.filters', 'uses' => 'PayoutHoldController@filters', 'middleware' => 'can:admin.payout_hold.filters']);
        Route::post('/', ['as' => 'admin.payout_hold.store', 'uses' => 'PayoutHoldController@store', 'middleware' => 'can:admin.payout_hold.store']);
        Route::post('/{id}/release', ['as' => 'admin.payout_hold.release', 'uses' => 'PayoutHoldController@release', 'middleware' => 'can:admin.payout_hold.release'])->where('id', '[0-9]+');
    });

    Route::prefix('tcs-report')->group(function () {
        Route::get('/', ['as' => 'admin.tcs_report.index', 'uses' => 'TcsReportController@index', 'middleware' => 'can:admin.tcs_report.index']);
        Route::get('/export', ['as' => 'admin.tcs_report.export', 'uses' => 'TcsReportController@export', 'middleware' => 'can:admin.tcs_report.export']);
    });

    Route::prefix('cod-remittance')->group(function () {
        Route::get('/', ['as' => 'admin.cod_remittance.index', 'uses' => 'CodRemittanceController@index', 'middleware' => 'can:admin.cod_remittance.index']);
        Route::post('/filters', ['as' => 'admin.cod_remittance.filters', 'uses' => 'CodRemittanceController@filters', 'middleware' => 'can:admin.cod_remittance.filters']);
        Route::post('/upload', ['as' => 'admin.cod_remittance.upload', 'uses' => 'CodRemittanceController@upload', 'middleware' => 'can:admin.cod_remittance.upload']);
        Route::get('/{id}', ['as' => 'admin.cod_remittance.show', 'uses' => 'CodRemittanceController@show', 'middleware' => 'can:admin.cod_remittance.show'])->where('id', '[0-9]+');
    });

    Route::prefix('reseller-withdrawal')->group(function () {
        Route::get('/', ['as' => 'admin.reseller_withdrawal.index', 'uses' => 'ResellerWithdrawalController@index', 'middleware' => 'can:admin.reseller_withdrawal.index']);
        Route::post('/filters', ['as' => 'admin.reseller_withdrawal.filters', 'uses' => 'ResellerWithdrawalController@filters', 'middleware' => 'can:admin.reseller_withdrawal.filters']);
        Route::post('/{id}/mark-paid', ['as' => 'admin.reseller_withdrawal.mark_paid', 'uses' => 'ResellerWithdrawalController@markPaid', 'middleware' => 'can:admin.reseller_withdrawal.mark_paid'])->where('id', '[0-9]+');
        Route::post('/{id}/mark-failed', ['as' => 'admin.reseller_withdrawal.mark_failed', 'uses' => 'ResellerWithdrawalController@markFailed', 'middleware' => 'can:admin.reseller_withdrawal.mark_failed'])->where('id', '[0-9]+');
    });

    Route::prefix('tax-document')->group(function () {
        Route::get('/', ['as' => 'admin.tax_document.index', 'uses' => 'TaxDocumentController@index', 'middleware' => 'can:admin.tax_document.index']);
        Route::post('/filters', ['as' => 'admin.tax_document.filters', 'uses' => 'TaxDocumentController@filters', 'middleware' => 'can:admin.tax_document.filters']);
        Route::post('/fee-invoices', ['as' => 'admin.tax_document.fee_invoices', 'uses' => 'TaxDocumentController@feeInvoices', 'middleware' => 'can:admin.tax_document.regenerate']);
        Route::get('/{id}/download', ['as' => 'admin.tax_document.download', 'uses' => 'TaxDocumentController@download', 'middleware' => 'can:admin.tax_document.download'])->where('id', '[0-9]+');
        Route::post('/{id}/regenerate', ['as' => 'admin.tax_document.regenerate', 'uses' => 'TaxDocumentController@regenerate', 'middleware' => 'can:admin.tax_document.regenerate'])->where('id', '[0-9]+');
    });
});
