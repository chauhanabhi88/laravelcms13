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

Route::prefix('cron')->group(function () {
    Route::get('/', [
        'as' => 'admin.cron.index',
        'uses' => 'CronController@index',
        'middleware' => 'can:admin.cron.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.cron.filters',
        'uses' => 'CronController@filters',
        'middleware' => 'can:admin.cron.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.cron.create',
        'uses' => 'CronController@create',
        'middleware' => 'can:admin.cron.create',
    ]);

    Route::post('/', [
        'as' => 'admin.cron.store',
        'uses' => 'CronController@store',
        'middleware' => 'can:admin.cron.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.cron.edit',
        'uses' => 'CronController@edit',
        'middleware' => 'can:admin.cron.edit',
    ]);

    Route::put('/{id}', [
        'as' => 'admin.cron.update',
        'uses' => 'CronController@update',
        'middleware' => 'can:admin.cron.edit',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.cron.delete',
        'uses' => 'CronController@delete',
        'middleware' => 'can:admin.cron.delete',
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.cron.mass_delete',
        'uses' => 'CronController@massDelete',
        'middleware' => 'can:admin.cron.mass_delete',
    ]);

    Route::post('/update_status', [
        'as' => 'admin.cron.update_status',
        'uses' => 'CronController@updateStatus',
        'middleware' => 'can:admin.cron.edit',
    ]);

    Route::get('/runCron/{command}', [
        'as' => 'admin.cron.runCron',
        'uses' => 'CronController@runCron',
        'middleware' => 'can:admin.cron.runCron',
    ]);

    Route::post('/schedule/filters/{id}', [
        'as' => 'admin.cron_schedule.filters',
        'uses' => 'CronController@scheduleFilters',
        'middleware' => 'can:admin.cron_schedule.filters',
    ]);

    Route::delete('/schedule/delete/{id}', [
        'as' => 'admin.cron_schedule.delete',
        'uses' => 'CronController@scheduleDelete',
        'middleware' => 'can:admin.cron_schedule.delete',
    ]);
});

Route::prefix('schedule')->group(function () {

    Route::get('/', [
        'as' => 'admin.schedule.index',
        'uses' => 'CronScheduleController@index',
        'middleware' => 'can:admin.schedule.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.schedule.filters',
        'uses' => 'CronScheduleController@filters',
        'middleware' => 'can:admin.schedule.filters',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.schedule.delete',
        'uses' => 'CronScheduleController@delete',
        'middleware' => 'can:admin.schedule.delete',
    ]);

    Route::delete('/massDelete/{id}', [
        'as' => 'admin.schedule.mass_delete',
        'uses' => 'CronScheduleController@massDelete',
        'middleware' => 'can:admin.schedule.mass_delete',
    ]);

});
