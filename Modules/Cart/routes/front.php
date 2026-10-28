<?php

/*
|--------------------------------------------------------------------------
| Storefront cart (M10)
|--------------------------------------------------------------------------
|
| Loaded before the Pages module's /{slug} catch-all (lower module priority). Item routes
| only reach lines of the visitor's own cart (404 otherwise). Every action answers JSON to
| fetch() and redirects to the cart page for plain form posts.
|
*/

Route::get('/cart', [
    'as' => 'cart.index',
    'uses' => 'CartController@index',
]);

Route::post('/cart/items', [
    'as' => 'cart.add',
    'uses' => 'CartController@add',
    'middleware' => 'throttle:30,1',
]);

Route::patch('/cart/items/{item}', [
    'as' => 'cart.update',
    'uses' => 'CartController@update',
    'middleware' => 'throttle:60,1',
])->where('item', '[0-9]+');

Route::delete('/cart/items/{item}', [
    'as' => 'cart.remove',
    'uses' => 'CartController@remove',
])->where('item', '[0-9]+');

Route::post('/cart/items/{item}/save', [
    'as' => 'cart.save_for_later',
    'uses' => 'CartController@saveForLater',
])->where('item', '[0-9]+');

Route::post('/cart/items/{item}/unsave', [
    'as' => 'cart.move_to_cart',
    'uses' => 'CartController@moveToCart',
])->where('item', '[0-9]+');

Route::post('/cart/items/{item}/wishlist', [
    'as' => 'cart.move_to_wishlist',
    'uses' => 'CartController@moveToWishlist',
])->where('item', '[0-9]+');

Route::post('/cart/buy-now', [
    'as' => 'cart.buy_now',
    'uses' => 'CartController@buyNow',
    'middleware' => 'throttle:30,1',
]);

Route::get('/cart/drawer', [
    'as' => 'cart.drawer',
    'uses' => 'CartController@drawer',
    'middleware' => 'throttle:60,1',
]);

Route::get('/cart/count', [
    'as' => 'cart.count',
    'uses' => 'CartController@count',
    'middleware' => 'throttle:60,1',
]);

Route::post('/cart/pincode', [
    'as' => 'cart.pincode',
    'uses' => 'CartController@pincode',
    'middleware' => 'throttle:30,1',
]);

/*
| "Move to cart" on the wishlist page (Customer M08 shows the button once this route exists).
*/
Route::post('/cart/from-wishlist', [
    'as' => 'cart.wishlist.move',
    'uses' => 'CartController@moveFromWishlist',
    'middleware' => ['auth:customer', 'throttle:30,1'],
]);
