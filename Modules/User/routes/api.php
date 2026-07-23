<?php

use Illuminate\Http\Request;

Route::middleware('auth:users')->prefix('user')->group(function () {
    Route::get('/', [
        'as' => 'api_user.index',
        'uses' => 'IndexController@index',
        'middleware' => 'scope:users',
    ]);
});

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);
        Route::prefix($version.'/admin')->group(function () use ($upperCaseVersion) {
            Route::post('/login', [
                'as' => 'admin.login',
                'uses' => $upperCaseVersion.'\UserController@login',
                'middleware' => 'throttle:20,1',
            ]);

            Route::get('/list', [
                'as' => 'api.user.list',
                'uses' => $upperCaseVersion.'\UserController@index',
                'middleware' => ['auth:users', 'can:admin.user.index'],
            ]);

            Route::post('/filter', [
                'as' => 'api.user.filter',
                'uses' => $upperCaseVersion.'\UserController@filters',
                'middleware' => ['auth:users', 'can:admin.user.filters'],
            ]);

            Route::post('/store', [
                'as' => 'api.user.store',
                'uses' => $upperCaseVersion.'\UserController@store',
                'middleware' => ['auth:users', 'can:admin.user.create'],
            ]);

            Route::post('/update_status', [
                'as' => 'api.user.update_status',
                'uses' => $upperCaseVersion.'\UserController@updateStatus',
                'middleware' => ['auth:users', 'can:admin.user.edit'],
            ]);

            Route::post('/mass_delete', [
                'as' => 'api.user.mass_delete',
                'uses' => $upperCaseVersion.'\UserController@massDelete',
                'middleware' => ['auth:users', 'can:admin.user.mass_delete'],
            ]);

            Route::delete('/delete/{id}', [
                'as' => 'api.user.delete',
                'uses' => $upperCaseVersion.'\UserController@delete',
                'middleware' => ['auth:users', 'can:admin.user.delete'],
            ]);

            Route::get('/edit/{id}', [
                'as' => 'api.user.edit',
                'uses' => $upperCaseVersion.'\UserController@edit',
                'middleware' => ['auth:users', 'can:admin.user.edit'],
            ]);
            Route::post('/{id}', [
                'as' => 'api.user.update',
                'uses' => $upperCaseVersion.'\UserController@update',
                'middleware' => ['auth:users', 'can:admin.user.edit'],
            ]);

            Route::middleware('auth:users')->get('/', function (Request $request) {
                $user = $request->user();

                // $permissions = $user->getPermissions();
                return response()->json(['success' => true, 'user' => $user]);
            });

            Route::middleware('auth:users')->get('/permissions', function (Request $request) {
                $user = $request->user();
                $permissions = $user->getPermissions();

                return response()->json(['success' => true, 'permissions' => $permissions]);
            });

        });

    }
}
