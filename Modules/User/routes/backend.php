<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('user')->group(function() {
    Route::get('/', [
        'as' => 'admin.user.index',
        'uses' => 'UserController@index',
        'middleware' => 'can:admin.user.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.user.filters',
        'uses' => 'UserController@filters',
        'middleware' => 'can:admin.user.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.user.create',
        'uses' => 'UserController@create',
        'middleware' => 'can:admin.user.create'
    ]);

    Route::post('/', [
        'as' => 'admin.user.store',
        'uses' => 'UserController@store',
        'middleware' => 'can:admin.user.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.user.edit',
        'uses' => 'UserController@edit',
        'middleware' => 'can:admin.user.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.user.update',
        'uses' => 'UserController@update',
        'middleware' => 'can:admin.user.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.user.delete',
        'uses' => 'UserController@delete',
        'middleware' => 'can:admin.user.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.user.mass_delete',
        'uses' => 'UserController@massDelete',
        'middleware' => 'can:admin.user.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.user.update_status',
        'uses' => 'UserController@updateStatus',
        'middleware' => 'can:admin.user.edit'
    ]);

    /**
     * edit admin profile
     */
    Route::get('/editProfile', [
        'as' => 'admin.user.editProfile',
        'uses' => 'UserController@editProfile',
        'middleware' => 'can:admin.user.editProfile'
    ]);

    Route::put('/updateProfile/{id}', [
        'as' => 'admin.user.updateProfile',
        'uses' => 'UserController@updateProfile',
        'middleware' => 'can:admin.user.editProfile'
    ]);
});
Route::prefix("deleted_user")->group(function() {
    Route::get("/", [
        "as" => "admin.deleted_user.index",
        "uses" => "DeletedUserController@index",
        "middleware" => "can:admin.deleted_user.index"
    ]);

    Route::post("/filters", [
        "as" => "admin.deleted_user.filters",
        "uses" => "DeletedUserController@filters",
        "middleware" => "can:admin.deleted_user.filters"
    ]);

    Route::post('/massRestore', [
        'as' => 'admin.deleted_user.mass_restore',
        'uses' => 'DeletedUserController@massRestore',
        'middleware' => 'can:admin.deleted_user.mass_restore'
    ]);

    Route::post('/restore/{id}', [
        'as' => 'admin.deleted_user.restore',
        'uses' => 'DeletedUserController@restore',
        'middleware' => 'can:admin.deleted_user.restore'
    ]);

    Route::delete("/delete/{id}", [
        "as" => "admin.deleted_user.delete",
        "uses" => "DeletedUserController@delete",
        "middleware" => "can:admin.deleted_user.delete"
    ]);

    Route::delete("/massDelete", [
        "as" => "admin.deleted_user.mass_delete",
        "uses" => "DeletedUserController@massDelete",
        "middleware" => "can:admin.deleted_user.mass_delete"
    ]);
});
        