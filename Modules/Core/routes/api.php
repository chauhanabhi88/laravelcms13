<?php

/*
|--------------------------------------------------------------------------
| Core API (M20)
|--------------------------------------------------------------------------
|
| api/{version}/app/config: public bootstrap data for the mobile apps. Each module adds its own
| section through the "api.app_config" tag (Modules\Core\Contracts\ProvidesAppConfig).
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/app')
            ->middleware(['throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion, $version) {
                Route::get('/config', [
                    'as' => $version === 'v1' ? 'core_api.app.config' : "core_api.{$version}.app.config",
                    'uses' => $upperCaseVersion.'\AppConfigController@show',
                ]);
            });
    }
}
