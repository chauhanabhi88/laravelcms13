<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Promotions console (plan M18 §6): coupons and automatic offers (+ bulk unique codes), flash
| deals and the promotion report. Loaded with the admin prefix and the "backend" group. Every
| route carries its can: permission from config/permissions.php.
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

Route::prefix('promotion')->group(function () use ($crud) {
    Route::get('coupon/bulk', ['as' => 'admin.coupon.bulk', 'uses' => 'CouponController@bulk', 'middleware' => 'can:admin.coupon.bulk_generate']);
    Route::post('coupon/bulk', ['as' => 'admin.coupon.bulk_generate', 'uses' => 'CouponController@bulkGenerate', 'middleware' => 'can:admin.coupon.bulk_generate']);

    $crud('coupon', 'coupon', 'CouponController');
    $crud('flash-deal', 'flash_deal', 'FlashDealController');

    Route::get('report', ['as' => 'admin.promotion_report.index', 'uses' => 'PromotionReportController@index', 'middleware' => 'can:admin.promotion_report.index']);
});
