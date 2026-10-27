<?php

/*
|--------------------------------------------------------------------------
| Storefront routes
|--------------------------------------------------------------------------
|
| Buyer-facing discovery. Loaded before the Pages module (lower module priority), whose
| catch-all /{slug} would otherwise swallow /search and /recently-viewed. "homepage" keeps
| its name from the Pages module, which owned "/" before M06.
|
*/

Route::get('/', [
    'as' => 'homepage',
    'uses' => 'HomeController@index',
]);

Route::get('/c/{slug}', [
    'as' => 'storefront.category',
    'uses' => 'CategoryController@show',
])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

Route::get('/search', [
    'as' => 'storefront.search',
    'uses' => 'SearchController@index',
]);

Route::post('/search/click', [
    'as' => 'storefront.search.click',
    'uses' => 'SearchController@click',
    'middleware' => 'throttle:60,1',
]);

Route::get('/s/{storeSlug}', [
    'as' => 'storefront.store.show',
    'uses' => 'StoreController@show',
])->where('storeSlug', '[a-z0-9]+(?:-[a-z0-9]+)*');

Route::get('/collection/{slug}', [
    'as' => 'storefront.collection',
    'uses' => 'CollectionController@show',
])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

Route::get('/recently-viewed', [
    'as' => 'storefront.recently_viewed',
    'uses' => 'RecentlyViewedController@index',
    'middleware' => 'throttle:60,1',
]);
