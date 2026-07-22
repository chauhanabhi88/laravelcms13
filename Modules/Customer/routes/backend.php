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

Route::prefix('customer')->group(function() {
    Route::get('/', [
        'as' => 'admin.customer.index',
        'uses' => 'CustomerController@index',
        'middleware' => 'can:admin.customer.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.customer.filters',
        'uses' => 'CustomerController@filters',
        'middleware' => 'can:admin.customer.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.customer.create',
        'uses' => 'CustomerController@create',
        'middleware' => 'can:admin.customer.create'
    ]);

    Route::post('/', [
        'as' => 'admin.customer.store',
        'uses' => 'CustomerController@store',
        'middleware' => 'can:admin.customer.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.customer.edit',
        'uses' => 'CustomerController@edit',
        'middleware' => 'can:admin.customer.edit'
    ]);

    Route::post('/edit/address', [
        'as' => 'admin.customer.address',
        'uses' => 'CustomerController@saveAddress',
        'middleware' => 'can:admin.customer.address'
    ]);

    Route::put('/update-profile/{id}', [
        'as' => 'admin.customer.update',
        'uses' => 'CustomerController@update',
        'middleware' => 'can:admin.customer.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.customer.delete',
        'uses' => 'CustomerController@destroy',
        'middleware' => 'can:admin.customer.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.customer.mass_delete',
        'uses' => 'CustomerController@massDelete',
        'middleware' => 'can:admin.customer.mass_delete'
    ]);
    Route::post('/update_status', [
        'as' => 'admin.customer.update_status',
        'uses' => 'CustomerController@updateStatus',
        'middleware' => 'can:admin.customer.edit'
    ]); 
});

Route::prefix('customergroup')->group(function() {
    Route::get('/', [
        'as' => 'admin.customer.group.index',
        'uses' => 'CustomerGroupController@index',
        'middleware' => 'can:admin.customer.group.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.customer.group.filters',
        'uses' => 'CustomerGroupController@filters',
        'middleware' => 'can:admin.customer.group.filters'
    ]);

    Route::get('/create', [
        'as' => 'admin.customer.group.create',
        'uses' => 'CustomerGroupController@create',
        'middleware' => 'can:admin.customer.group.create'
    ]);

    Route::post('/', [
        'as' => 'admin.customer.group.store',
        'uses' => 'CustomerGroupController@store',
        'middleware' => 'can:admin.customer.group.create'
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.customer.group.edit',
        'uses' => 'CustomerGroupController@edit',
        'middleware' => 'can:admin.customer.group.edit'
    ]);

    Route::put('/{id}', [
        'as' => 'admin.customer.group.update',
        'uses' => 'CustomerGroupController@update',
        'middleware' => 'can:admin.customer.group.edit'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.customer.group.delete',
        'uses' => 'CustomerGroupController@destroy',
        'middleware' => 'can:admin.customer.group.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.customer.group.mass_delete',
        'uses' => 'CustomerGroupController@massDelete',
        'middleware' => 'can:admin.customer.group.mass_delete'
    ]);

    Route::post('/update_is_default', [
        'as' => 'admin.customer.group.update_is_default',
        'uses' => 'CustomerGroupController@updateIsDefault',
        'middleware' => 'can:admin.customer.group.edit'
    ]);
});

Route::prefix('deletedcustomer')->group(function() {

    Route::get('/', [
        'as' => 'admin.customer.deletedCustomer',
        'uses' => 'DeletedCustomerController@index',
        'middleware' => 'can:admin.customer.deletedCustomer'
    ]);

    Route::post('/filters', [
        'as' => 'admin.customer.deletedcustomerfilters',
        'uses' => 'DeletedCustomerController@filters',
        'middleware' => 'can:admin.customer.deletedcustomerfilters'
    ]);

    Route::post('/massRestore', [
        'as' => 'admin.customer.mass_restore',
        'uses' => 'DeletedCustomerController@massRestore',
        'middleware' => 'can:admin.customer.mass_restore'
    ]);

    Route::post('/restore/{id}', [
        'as' => 'admin.customer.restore',
        'uses' => 'DeletedCustomerController@restore',
        'middleware' => 'can:admin.customer.restore'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.deletedCustomer.delete',
        'uses' => 'DeletedCustomerController@destroy',
        'middleware' => 'can:admin.deletedCustomer.delete'
    ]);

    Route::delete('/massDelete', [
        'as' => 'admin.deletedCustomer.mass_delete',
        'uses' => 'DeletedCustomerController@massDelete',
        'middleware' => 'can:admin.deletedCustomer.mass_delete'
    ]);

});

Route::prefix('address')->group(function() {

    Route::get('/',[
        'as' => 'admin.address.get_address',
        'uses' => 'CustomerController@getAddress'
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.address.delete',
        'uses' => 'CustomerController@deleteAddress'
    ]);
});

Route::prefix('customeronlinelogs')->group(function() {
    Route::get('/', [
        'as' => 'admin.customerLog.index',
        'uses' => 'CustomerOnlineOfflineController@index',
        'middleware' => 'can:admin.customerLog.index'
    ]);

    // Route::post('/refreshGrid', [
    //     'as' => 'admin.customerLog.refreshGrid',
    //     'uses' => 'CustomerOnlineOfflineController@refreshGrid',
    //     'middleware' => 'can:admin.customerLog.refreshGrid'
    // ]);

    Route::post('/filters', [
        'as' => 'admin.customerLog.filters',
        'uses' => 'CustomerOnlineOfflineController@filters',
        'middleware' => 'can:admin.customerLog.filters'
    ]);
});


Route::prefix('customerloginlogs')->group(function() {
    Route::get('/', [
        'as' => 'admin.customerloginlog.index',
        'uses' => 'CustomerLoginLogController@index',
        'middleware' => 'can:admin.customerloginlog.index'
    ]);

    Route::post('/filters', [
        'as' => 'admin.customerloginlog.filters',
        'uses' => 'CustomerLoginLogController@filters',
        'middleware' => 'can:admin.customerloginlog.filters'
    ]);

    Route::post('/export', [
        'as' => 'admin.customerloginlog.export',
        'uses' => 'CustomerLoginLogController@export',
        'middleware' => 'can:admin.customerloginlog.export'
    ]);
});