<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, read-only storefront API (api/{version}/...): autocomplete, product listing and
| search with facets, the home page sections and recently viewed cards.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        $name = fn (string $route): string => $version === 'v1' ? "storefront_api.{$route}" : "storefront_api.{$version}.{$route}";

        Route::prefix($version)->group(function () use ($upperCaseVersion, $name) {
            Route::get('/autocomplete', [
                'as' => $name('autocomplete'),
                'uses' => $upperCaseVersion.'\AutocompleteController@index',
                'middleware' => 'throttle:storefront-autocomplete',
            ]);

            Route::middleware('throttle:60,1')->group(function () use ($upperCaseVersion, $name) {
                Route::get('/products', [
                    'as' => $name('search'),
                    'uses' => $upperCaseVersion.'\SearchController@index',
                ]);

                Route::get('/home', [
                    'as' => $name('home'),
                    'uses' => $upperCaseVersion.'\HomeController@index',
                ]);

                Route::get('/recently-viewed', [
                    'as' => $name('recently_viewed'),
                    'uses' => $upperCaseVersion.'\RecentlyViewedController@index',
                ]);
            });
        });
    }
}
