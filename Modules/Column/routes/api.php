<?php

use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

if ($apiVersions = config("core.api_versions")) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::prefix($version .'/column')->group(function () use ($upperCaseVersion) {

            Route::get('/', [
                'as' => 'column_api.saveDefaultColumns',
                'uses' => $upperCaseVersion.'\ColumnController@saveDefaultColumns',
                'middleware' => 'auth:users'
            ]);
        });

    }
}
