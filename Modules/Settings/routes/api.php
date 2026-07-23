<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::middleware('auth:api')->prefix($version.'/settings')->group(function () use ($upperCaseVersion) {
            Route::get('/', [
                'as' => 'settings_api.index',
                'uses' => $upperCaseVersion.'\SettingsController@index',
            ]);

        });

    }
}
