<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Analytics & reports (plan M19 §6). Loaded with the admin prefix and the "backend" group.
| Every report has its own can: permission so roles can be scoped (Finance sees payments,
| Logistics Ops sees fulfilment). Exports also need the exported report's permission
| (checked in ReportExportController).
|
*/

Route::prefix('report')->group(function () {
    $reports = [
        'dashboard' => 'ReportDashboardController',
        'sales' => 'SalesReportController',
        'fulfilment' => 'FulfilmentReportController',
        'returns' => 'ReturnsReportController',
        'sellers' => 'SellerLeaderboardController',
        'products' => 'ProductReportController',
        'geography' => 'GeographyReportController',
        'payments' => 'PaymentMixReportController',
        'search' => 'SearchReportController',
    ];

    foreach ($reports as $report => $controller) {
        Route::get($report === 'dashboard' ? '/' : $report, [
            'as' => "admin.report.{$report}",
            'uses' => "{$controller}@index",
            'middleware' => "can:admin.report.{$report}",
        ]);
    }

    Route::get('exports', [
        'as' => 'admin.report.export',
        'uses' => 'ReportExportController@index',
        'middleware' => 'can:admin.report.export',
    ]);
    Route::get('exports/start', [
        'as' => 'admin.report.export_start',
        'uses' => 'ReportExportController@start',
        'middleware' => ['can:admin.report.export', 'throttle:20,1'],
    ]);
    Route::get('exports/{id}/download', [
        'as' => 'admin.report.export_download',
        'uses' => 'ReportExportController@download',
        'middleware' => 'can:admin.report.export',
    ])->where('id', '[0-9]+');
});
