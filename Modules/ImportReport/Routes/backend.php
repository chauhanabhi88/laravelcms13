<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('importreport')->group(function() {
    Route::get('/', [
        'as' => 'admin.importreport.index',
        'uses' => 'IndexController@index',
        'middleware' => 'can:admin.importreport.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.importreport.filters',
        'uses' => 'IndexController@filters',
        'middleware' => 'can:admin.importreport.filters'
    ]);

    Route::post('/export', [
        'as' => 'admin.importreport.export',
        'uses' => 'IndexController@export',
        'middleware' => 'can:admin.importreport.export'
    ]);

    Route::post("/import_competitor_mapping", [
        "as"	=>	"admin.importreport.import_competitor_mapping",
        "uses"	=>	"IndexController@importCompetitorMapping",
        "middleware" =>	"can:admin.importreport.import_competitor_mapping"
    ]);

    Route::post("/prisync_vertical_report", [
        "as"	=>	"admin.importreport.prisync_vertical_report",
        "uses"	=>	"IndexController@prisyncVerticalReport",
        "middleware" =>	"can:admin.importreport.prisync_vertical_report"
    ]);

});
