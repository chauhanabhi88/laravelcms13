<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated, read-only pincode lookup (api/{version}/pincode/{pincode}),
| used by the address form, PDP pincode check and checkout.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::middleware(['throttle:api', 'api.envelope'])->prefix($version.'/pincode')->group(function () use ($upperCaseVersion, $version) {
            Route::get('/{pincode}', [
                'as' => $version === 'v1' ? 'directory_api.pincode.show' : "directory_api.{$version}.pincode.show",
                'uses' => $upperCaseVersion.'\PincodeController@show',
            ])->where('pincode', '[0-9]{6}');
        });
    }
}
