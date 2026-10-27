<?php

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
|
| Public blog. Loaded by the module RouteServiceProvider inside the "web"
| group (plus the {locale} prefix when core.translation_front is on).
|
*/

Route::prefix('blog')->group(function () {
    Route::get('/', [
        'as' => 'blog.index',
        'uses' => 'BlogController@index',
    ]);
    Route::get('/category/{slug}', [
        'as' => 'blog.category',
        'uses' => 'BlogController@category',
    ]);
    Route::get('/{slug}', [
        'as' => 'blog.show',
        'uses' => 'BlogController@show',
    ]);
    Route::post('/{slug}/comments', [
        'as' => 'blog.comments.store',
        'uses' => 'BlogCommentController@store',
        'middleware' => ['auth:customer', 'throttle:10,1'],
    ]);
});
