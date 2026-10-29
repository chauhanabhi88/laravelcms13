<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Review moderation (FR-REV-002, plan M16 §5): the queue (waiting + reported reviews), the
| detail with photos and reports, publish / reject / hide / delete. Loaded with the admin
| prefix and the "backend" group.
|
*/

Route::prefix('product-review')->group(function () {
    Route::get('/', [
        'as' => 'admin.product_review.index',
        'uses' => 'ProductReviewController@index',
        'middleware' => 'can:admin.product_review.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.product_review.filters',
        'uses' => 'ProductReviewController@filters',
        'middleware' => 'can:admin.product_review.filters',
    ]);

    Route::get('/{id}', [
        'as' => 'admin.product_review.show',
        'uses' => 'ProductReviewController@show',
        'middleware' => 'can:admin.product_review.show',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/publish', [
        'as' => 'admin.product_review.publish',
        'uses' => 'ProductReviewController@publish',
        'middleware' => 'can:admin.product_review.publish',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/reject', [
        'as' => 'admin.product_review.reject',
        'uses' => 'ProductReviewController@reject',
        'middleware' => 'can:admin.product_review.reject',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/hide', [
        'as' => 'admin.product_review.hide',
        'uses' => 'ProductReviewController@hide',
        'middleware' => 'can:admin.product_review.hide',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/delete', [
        'as' => 'admin.product_review.delete',
        'uses' => 'ProductReviewController@delete',
        'middleware' => 'can:admin.product_review.delete',
    ])->where('id', '[0-9]+');
});
