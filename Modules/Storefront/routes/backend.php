<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Storefront merchandising admin: home page sections, product collections and the search
| report. Loaded with the admin prefix and the "backend" middleware group.
|
*/

Route::prefix('storefront/home-section')->group(function () {
    Route::get('/', [
        'as' => 'admin.home_section.index',
        'uses' => 'HomeSectionController@index',
        'middleware' => 'can:admin.home_section.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.home_section.filters',
        'uses' => 'HomeSectionController@filters',
        'middleware' => 'can:admin.home_section.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.home_section.create',
        'uses' => 'HomeSectionController@create',
        'middleware' => 'can:admin.home_section.create',
    ]);

    Route::post('/', [
        'as' => 'admin.home_section.store',
        'uses' => 'HomeSectionController@store',
        'middleware' => 'can:admin.home_section.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.home_section.edit',
        'uses' => 'HomeSectionController@edit',
        'middleware' => 'can:admin.home_section.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.home_section.update',
        'uses' => 'HomeSectionController@update',
        'middleware' => 'can:admin.home_section.edit',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.home_section.update_status',
        'uses' => 'HomeSectionController@updateStatus',
        'middleware' => 'can:admin.home_section.update_status',
    ]);

    Route::post('/reorder', [
        'as' => 'admin.home_section.reorder',
        'uses' => 'HomeSectionController@reorder',
        'middleware' => 'can:admin.home_section.reorder',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.home_section.delete',
        'uses' => 'HomeSectionController@delete',
        'middleware' => 'can:admin.home_section.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.home_section.mass_delete',
        'uses' => 'HomeSectionController@massDelete',
        'middleware' => 'can:admin.home_section.mass_delete',
    ]);
});

Route::prefix('storefront/collection')->group(function () {
    Route::get('/', [
        'as' => 'admin.product_collection.index',
        'uses' => 'ProductCollectionController@index',
        'middleware' => 'can:admin.product_collection.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.product_collection.filters',
        'uses' => 'ProductCollectionController@filters',
        'middleware' => 'can:admin.product_collection.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.product_collection.create',
        'uses' => 'ProductCollectionController@create',
        'middleware' => 'can:admin.product_collection.create',
    ]);

    Route::post('/', [
        'as' => 'admin.product_collection.store',
        'uses' => 'ProductCollectionController@store',
        'middleware' => 'can:admin.product_collection.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.product_collection.edit',
        'uses' => 'ProductCollectionController@edit',
        'middleware' => 'can:admin.product_collection.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.product_collection.update',
        'uses' => 'ProductCollectionController@update',
        'middleware' => 'can:admin.product_collection.edit',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.product_collection.update_status',
        'uses' => 'ProductCollectionController@updateStatus',
        'middleware' => 'can:admin.product_collection.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.product_collection.delete',
        'uses' => 'ProductCollectionController@delete',
        'middleware' => 'can:admin.product_collection.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.product_collection.mass_delete',
        'uses' => 'ProductCollectionController@massDelete',
        'middleware' => 'can:admin.product_collection.mass_delete',
    ]);
});

Route::get('storefront/search-report', [
    'as' => 'admin.search_report.index',
    'uses' => 'SearchReportController@index',
    'middleware' => 'can:admin.search_report.index',
]);
