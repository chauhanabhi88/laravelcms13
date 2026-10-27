<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated, read-only banner API (api/{version}/public/banners/{code}).
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::middleware('throttle:60,1')->prefix($version.'/public/banners')->group(function () use ($upperCaseVersion, $version) {
            Route::get('/{code}', [
                'as' => "api.public.{$version}.banners.group",
                'uses' => $upperCaseVersion.'\PublicBannerController@group',
            ]);
        });
    }
}
