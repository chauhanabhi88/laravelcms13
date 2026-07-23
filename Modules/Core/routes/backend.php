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

Route::get('/reset_filter/{module_name}/{session_key?}', [
    'as' => 'admin.reset_filter',
    'uses' => 'ModuleController@resetFilter',
]);

Route::prefix('module')->group(function () {
    Route::get('/', [
        'as' => 'admin.module.index',
        'uses' => 'ModuleController@index',
        'middleware' => 'can:admin.module.index',
    ]);

    Route::put('/', [
        'as' => 'admin.module.update',
        'uses' => 'ModuleController@update',
        'middleware' => 'can:admin.module.update',
    ]);

    Route::post('/createModule', [
        'as' => 'admin.module.createmodule',
        'uses' => 'ModuleController@create',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/clearCache', [
        'as' => 'admin.module.clearCache',
        'uses' => 'ModuleController@clearCache',
        'middleware' => 'can:admin.module.clear_all_cache',
    ]);

    Route::post('/publish', [
        'as' => 'admin.module.publish',
        'uses' => 'ModuleController@publish',
        'middleware' => 'can:admin.module.publish',
    ]);

    Route::post('/migrate', [
        'as' => 'admin.module.migrate',
        'uses' => 'ModuleController@migrate',
        'middleware' => 'can:admin.module.migrate',
    ]);

    Route::post('/translation', [
        'as' => 'admin.module.publishtranslation',
        'uses' => 'ModuleController@publishTranslation',
        'middleware' => 'can:admin.module.publishtranslation',
    ]);

    Route::post('/config', [
        'as' => 'admin.module.publishconfig',
        'uses' => 'ModuleController@publishConfig',
        'middleware' => 'can:admin.module.publishconfig',
    ]);

    Route::post('/seed', [
        'as' => 'admin.module.seed',
        'uses' => 'ModuleController@seed',
        'middleware' => 'can:admin.module.seed',
    ]);

    Route::post('/getColumns', [
        'as' => 'admin.module.getcolumns',
        'uses' => 'ModuleController@getColumns',
        'middleware' => 'can:admin.module.index',
    ]);

    Route::post('/create', [
        'as' => 'admin.module.create',
        'uses' => 'ModuleController@createModule',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/create/folder', [
        'as' => 'admin.module.createfolder',
        'uses' => 'ModuleController@createFolder',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/createFolder', [
        'as' => 'admin.module.savefolder',
        'uses' => 'ModuleController@saveFolder',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/getEntities', [
        'as' => 'admin.module.getentities',
        'uses' => 'ModuleController@getEntities',
        'middleware' => 'can:admin.module.seed',
    ]);

    Route::post('/createSeeder', [
        'as' => 'admin.module.createseeder',
        'uses' => 'ModuleController@createSeed',
        'middleware' => 'can:admin.module.seed',
    ]);

    Route::post('/enable', [
        'as' => 'admin.module.enable',
        'uses' => 'ModuleController@enable',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/getDependentModules', [
        'as' => 'admin.module.getdependentmodules',
        'uses' => 'ModuleController@getDependentModules',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/addDependency', [
        'as' => 'admin.module.adddependency',
        'uses' => 'ModuleController@addDependency',
        'middleware' => 'can:admin.module.create',
    ]);

    Route::post('/maintenance/up', [
        'as' => 'admin.module.maintenance_up',
        'uses' => 'ModuleController@maintenanceModeUp',
        'middleware' => 'can:admin.module.maintenance_up',
    ]);

    Route::post('/maintenance/down', [
        'as' => 'admin.module.maintenance_down',
        'uses' => 'ModuleController@maintenanceModeDown',
        'middleware' => 'can:admin.module.maintenance_down',
    ]);

});

Route::prefix('entity')->group(function () {
    Route::get('/create/{module}', [
        'as' => 'admin.entity.manage',
        'uses' => 'EntityController@manage',
        'middleware' => 'can:admin.entity.manage',
    ]);

    Route::post('/', [
        'as' => 'admin.entity.save',
        'uses' => 'EntityController@save',
        'middleware' => 'can:admin.entity.save',
    ]);

    // Route::put('/', [
    //     'as' => 'admin.entity.entity',
    //     'uses' => 'EntityController@update',
    //     'middleware' => 'can:admin.entity.update'
    // ]);

    Route::post('loadEntity', [
        'as' => 'admin.entity.loadEntity',
        'uses' => 'EntityController@loadEntity',
        'middleware' => 'can:admin.entity.manage',
    ]);

    Route::post('loadColumns', [
        'as' => 'admin.entity.loadColumns',
        'uses' => 'EntityController@loadColumns',
        'middleware' => 'can:admin.entity.manage',
    ]);

    Route::delete('/entity', [
        'as' => 'admin.entity.entity',
        'uses' => 'EntityController@delete',
        'middleware' => 'can:admin.entity.delete',
    ]);

    Route::put('/edit', [
        'as' => 'admin.entity.edit',
        'uses' => 'EntityController@edit',
        'middleware' => 'can:admin.entity.manage',
    ]);

    Route::post('/create_migration', [
        'as' => 'admin.module.create_migration',
        'uses' => 'ModuleController@createMigration',
        'middleware' => 'can:admin.module.migrate',
    ]);
});

Route::prefix('summernote')->group(function () {
    Route::post('/image_upload_temp', [
        'as' => 'admin.summernote.image_upload_temp',
        'uses' => 'SummernoteController@imageUpload',
    ]);
});
