<?php

/*
|--------------------------------------------------------------------------
| Reviews on the storefront and in the customer account (M16)
|--------------------------------------------------------------------------
|
| The full reviews page of a product sits under its product page URL. Helpful votes and abuse
| reports need a signed-in customer (JSON for the storefront script, redirect otherwise).
| Writing and editing reviews lives next to My Orders under /customer.
|
*/

Route::get('/p/{slugId}/reviews', [
    'as' => 'storefront.product.reviews',
    'uses' => 'ReviewController@index',
])->where('slugId', '[a-z0-9-]+-[0-9]+');

Route::middleware('auth:customer')->group(function () {
    Route::post('/reviews/{id}/helpful', [
        'as' => 'review.vote',
        'uses' => 'ReviewVoteController@toggle',
        'middleware' => 'throttle:30,1',
    ])->where('id', '[0-9]+');

    Route::post('/reviews/{id}/report', [
        'as' => 'review.report',
        'uses' => 'ReviewReportController@store',
        'middleware' => 'throttle:10,1',
    ])->where('id', '[0-9]+');

    Route::prefix('customer/reviews')->group(function () {
        Route::get('/', [
            'as' => 'customer.reviews.index',
            'uses' => 'Account\MyReviewController@index',
        ]);

        Route::get('/write/{orderItem}', [
            'as' => 'customer.reviews.create',
            'uses' => 'Account\MyReviewController@create',
        ])->where('orderItem', '[0-9]+');

        Route::post('/write/{orderItem}', [
            'as' => 'customer.reviews.store',
            'uses' => 'Account\MyReviewController@store',
            'middleware' => 'throttle:10,1',
        ])->where('orderItem', '[0-9]+');

        Route::get('/{review}/edit', [
            'as' => 'customer.reviews.edit',
            'uses' => 'Account\MyReviewController@edit',
        ])->where('review', '[0-9]+');

        Route::put('/{review}/edit', [
            'as' => 'customer.reviews.update',
            'uses' => 'Account\MyReviewController@update',
            'middleware' => 'throttle:10,1',
        ])->where('review', '[0-9]+');
    });
});
