<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public storefront API (api/{version}/...): autocomplete, product listing and search with
| facets, the home page sections, recently viewed cards, the product page payload and the
| delivery check.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        $name = fn (string $route): string => $version === 'v1' ? "storefront_api.{$route}" : "storefront_api.{$version}.{$route}";

        Route::prefix($version)->middleware('api.envelope')->group(function () use ($upperCaseVersion, $name) {
            Route::get('/autocomplete', [
                'as' => $name('autocomplete'),
                'uses' => $upperCaseVersion.'\AutocompleteController@index',
                'middleware' => ['throttle:api-search', 'throttle:storefront-autocomplete'],
            ]);

            Route::get('/products', [
                'as' => $name('search'),
                'uses' => $upperCaseVersion.'\SearchController@index',
                'middleware' => 'throttle:api-search',
            ]);

            Route::middleware('throttle:api')->group(function () use ($upperCaseVersion, $name) {

                Route::get('/home', [
                    'as' => $name('home'),
                    'uses' => $upperCaseVersion.'\HomeController@index',
                ]);

                Route::get('/recently-viewed', [
                    'as' => $name('recently_viewed'),
                    'uses' => $upperCaseVersion.'\RecentlyViewedController@index',
                ]);

                Route::get('/products/{id}', [
                    'as' => $name('product.show'),
                    'uses' => $upperCaseVersion.'\ProductController@show',
                ])->where('id', '[0-9]+');

                Route::get('/products/{id}/similar', [
                    'as' => $name('product.similar'),
                    'uses' => $upperCaseVersion.'\SimilarProductsController@index',
                ])->where('id', '[0-9]+');
            });

            Route::post('/delivery-check', [
                'as' => $name('delivery_check'),
                'uses' => $upperCaseVersion.'\DeliveryCheckController@check',
                'middleware' => 'throttle:storefront-delivery-check',
            ]);
        });
    }
}
