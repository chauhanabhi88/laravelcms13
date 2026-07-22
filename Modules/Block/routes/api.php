<?php

use Illuminate\Http\Request;

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
if ($apiVersions = config("core.api_versions")) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::middleware(['auth:customer'])->prefix($version . '/block')->group(function () use ($upperCaseVersion) {
            Route::get('/{slug}', [
                'as' => 'block.index',
                'uses' => $upperCaseVersion . '\BlockController@index'
            ]);
        });
    }
}

