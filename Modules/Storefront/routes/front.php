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

/*
| Product detail page (M07). "storefront.product.show" is the name StorefrontUrls::product()
| already links to; the slug part is checked against the product and 301-redirected.
*/
Route::get('/p/{slugId}', [
    'as' => 'storefront.product.show',
    'uses' => 'ProductController@show',
])->where('slugId', '[a-z0-9-]+-[0-9]+');

Route::get('/p/{id}/similar', [
    'as' => 'storefront.product.similar',
    'uses' => 'ProductController@similar',
    'middleware' => 'throttle:60,1',
])->where('id', '[0-9]+');

Route::get('/p/{id}/availability', [
    'as' => 'storefront.product.availability',
    'uses' => 'ProductController@availability',
    'middleware' => 'throttle:60,1',
])->where('id', '[0-9]+');

Route::post('/delivery-check', [
    'as' => 'storefront.delivery_check',
    'uses' => 'DeliveryCheckController@check',
    'middleware' => 'throttle:storefront-delivery-check',
]);

Route::get('/recently-viewed', [
    'as' => 'storefront.recently_viewed',
    'uses' => 'RecentlyViewedController@index',
    'middleware' => 'throttle:60,1',
]);
