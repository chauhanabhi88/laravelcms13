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

Route::middleware('auth:api')->get('/pages', function (Request $request) {
    return $request->user();
});

if ($apiVersions = config("core.api_versions")) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::middleware(['auth:customer'])->prefix($version . '/page')->group(function () use ($upperCaseVersion) {
            Route::get('/{slug}', [
                'as' => 'page.index',
                'uses' => $upperCaseVersion . '\PageController@index'
            ]);
        });
    }
}