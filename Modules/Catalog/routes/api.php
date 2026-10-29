<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated, read-only catalog API (api/{version}/categories, /brands),
| used by the storefront, the seller listing form and the mobile apps.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        $name = fn (string $route): string => $version === 'v1' ? "catalog_api.{$route}" : "catalog_api.{$version}.{$route}";

        Route::middleware(['throttle:api', 'api.envelope'])->prefix($version)->group(function () use ($upperCaseVersion, $name) {
            Route::get('/categories', [
                'as' => $name('categories.tree'),
                'uses' => $upperCaseVersion.'\CategoryController@index',
            ]);

            Route::get('/categories/{slug}', [
                'as' => $name('categories.show'),
                'uses' => $upperCaseVersion.'\CategoryController@show',
            ])->where('slug', '[a-z0-9\-]+');

            Route::get('/brands', [
                'as' => $name('brands.index'),
                'uses' => $upperCaseVersion.'\BrandController@index',
            ]);
        });
    }
}
