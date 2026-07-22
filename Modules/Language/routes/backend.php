<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('language')->group(function() {
    Route::get('/', [
        'as' => 'admin.language.index',
        'uses' => 'LanguageController@index',
        'middleware' => 'can:admin.language.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.language.filters',
        'uses' => 'LanguageController@filters',
        'middleware' => 'can:admin.language.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.language.create',
        'uses' => 'LanguageController@create',
        'middleware' => 'can:admin.language.create'
    ]);

    Route::post('/', [
        'as' => 'admin.language.store',
        'uses' => 'LanguageController@store',
        'middleware' => 'can:admin.language.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.language.edit',
        'uses' => 'LanguageController@edit',
        'middleware' => 'can:admin.language.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.language.update',
        'uses' => 'LanguageController@update',
        'middleware' => 'can:admin.language.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.language.delete',
        'uses' => 'LanguageController@delete',
        'middleware' => 'can:admin.language.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.language.mass_delete',
        'uses' => 'LanguageController@massDelete',
        'middleware' => 'can:admin.language.mass_delete'
    ]);

    Route::post('/update_status', [
        'as' => 'admin.language.update_status',
        'uses' => 'LanguageController@updateStatus',
        'middleware' => 'can:admin.language.edit'
    ]);

    Route::get('/exporttranslation', [
        'as' => 'admin.language.exporttranslation',
        'uses' => 'LanguageController@exportTranslation',
        'middleware' => 'can:admin.language.export'
    ]);

    Route::post('/importtranslation', [
        'as' => 'admin.language.import',
        'uses' => 'LanguageController@importTranslation',
        'middleware' => 'can:admin.language.import'
    ]);

});

Route::prefix('translation')->group(function() {
    Route::get('/', [
        'as' => 'admin.translation.index',
        'uses' => 'TranslationController@index',
        'middleware' => 'can:admin.translation.index'
    ]);

    Route::post('/update', [
        'as' => 'admin.translation.update',
        'uses' => 'TranslationController@update',
        'middleware' => 'can:admin.translation.index'
    ]);
});
