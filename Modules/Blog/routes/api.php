<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated, read-only blog API (api/{version}/public/blog/...).
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::middleware('throttle:60,1')->prefix($version.'/public/blog')->group(function () use ($upperCaseVersion, $version) {
            Route::get('/posts', [
                'as' => "api.public.{$version}.blog.posts",
                'uses' => $upperCaseVersion.'\PublicBlogController@index',
            ]);
            Route::get('/posts/{slug}', [
                'as' => "api.public.{$version}.blog.posts.show",
                'uses' => $upperCaseVersion.'\PublicBlogController@show',
            ]);
            Route::get('/posts/{slug}/comments', [
                'as' => "api.public.{$version}.blog.posts.comments",
                'uses' => $upperCaseVersion.'\PublicBlogController@comments',
            ]);
            Route::get('/categories', [
                'as' => "api.public.{$version}.blog.categories",
                'uses' => $upperCaseVersion.'\PublicBlogController@categories',
            ]);
        });
    }
}
