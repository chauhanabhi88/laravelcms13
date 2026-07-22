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

Route::prefix('mail')->group(function() {
    Route::get('/', [
        'as' => 'admin.mail.index',
        'uses' => 'MailController@index',
        'middleware' => 'can:admin.mail.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.mail.filters',
        'uses' => 'MailController@filters',
        'middleware' => 'can:admin.mail.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.mail.create',
        'uses' => 'MailController@create',
        'middleware' => 'can:admin.mail.create'
    ]);

    Route::post('/', [
        'as' => 'admin.mail.store',
        'uses' => 'MailController@store',
        'middleware' => 'can:admin.mail.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.mail.edit',
        'uses' => 'MailController@edit',
        'middleware' => 'can:admin.mail.edit'
    ]);

    Route::get('/preview/{id}', [
        'as' => 'admin.mail.preview',
        'uses' => 'MailController@perview',
        'middleware' => 'can:admin.mail.preview'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.mail.update',
        'uses' => 'MailController@update',
        'middleware' => 'can:admin.mail.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.mail.delete',
        'uses' => 'MailController@delete',
        'middleware' => 'can:admin.mail.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.mail.mass_delete',
        'uses' => 'MailController@massDelete',
        'middleware' => 'can:admin.mail.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.mail.update_status',
        'uses' => 'MailController@updateStatus',
        'middleware' => 'can:admin.mail.edit'
    ]);
});


Route::prefix('mail_log')->group(function() {
    Route::get('/', [
        'as' => 'admin.mail_log.index',
        'uses' => 'MailLogController@index',
        'middleware' => 'can:admin.mail_log.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.mail_log.filters',
        'uses' => 'MailLogController@filters',
        'middleware' => 'can:admin.mail_log.filters'
    ]);

    
});