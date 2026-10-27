<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Catalog taxonomy admin: categories (tree + grid, attribute mapping), brands and size
| charts. Loaded with the admin prefix and the "backend" middleware group.
|
*/

Route::prefix('catalog/category')->group(function () {
    Route::get('/', [
        'as' => 'admin.catalog_category.index',
        'uses' => 'CatalogCategoryController@index',
        'middleware' => 'can:admin.catalog_category.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.catalog_category.filters',
        'uses' => 'CatalogCategoryController@filters',
        'middleware' => 'can:admin.catalog_category.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.catalog_category.create',
        'uses' => 'CatalogCategoryController@create',
        'middleware' => 'can:admin.catalog_category.create',
    ]);

    Route::post('/', [
        'as' => 'admin.catalog_category.store',
        'uses' => 'CatalogCategoryController@store',
        'middleware' => 'can:admin.catalog_category.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.catalog_category.edit',
        'uses' => 'CatalogCategoryController@edit',
        'middleware' => 'can:admin.catalog_category.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.catalog_category.update',
        'uses' => 'CatalogCategoryController@update',
        'middleware' => 'can:admin.catalog_category.edit',
    ])->where('id', '[0-9]+');

    Route::post('/move', [
        'as' => 'admin.catalog_category.move',
        'uses' => 'CatalogCategoryController@move',
        'middleware' => 'can:admin.catalog_category.move',
    ]);

    Route::post('/update_status', [
        'as' => 'admin.catalog_category.update_status',
        'uses' => 'CatalogCategoryController@updateStatus',
        'middleware' => 'can:admin.catalog_category.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.catalog_category.delete',
        'uses' => 'CatalogCategoryController@delete',
        'middleware' => 'can:admin.catalog_category.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.catalog_category.mass_delete',
        'uses' => 'CatalogCategoryController@massDelete',
        'middleware' => 'can:admin.catalog_category.mass_delete',
    ]);

    Route::get('/{id}/attributes', [
        'as' => 'admin.catalog_category.attributes',
        'uses' => 'CategoryAttributeController@edit',
        'middleware' => 'can:admin.catalog_category.attributes',
    ])->where('id', '[0-9]+');

    Route::post('/{id}/attributes', [
        'as' => 'admin.catalog_category.attributes_save',
        'uses' => 'CategoryAttributeController@save',
        'middleware' => 'can:admin.catalog_category.attributes',
    ])->where('id', '[0-9]+');
});

Route::prefix('catalog/brand')->group(function () {
    Route::get('/', [
        'as' => 'admin.catalog_brand.index',
        'uses' => 'CatalogBrandController@index',
        'middleware' => 'can:admin.catalog_brand.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.catalog_brand.filters',
        'uses' => 'CatalogBrandController@filters',
        'middleware' => 'can:admin.catalog_brand.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.catalog_brand.create',
        'uses' => 'CatalogBrandController@create',
        'middleware' => 'can:admin.catalog_brand.create',
    ]);

    Route::post('/', [
        'as' => 'admin.catalog_brand.store',
        'uses' => 'CatalogBrandController@store',
        'middleware' => 'can:admin.catalog_brand.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.catalog_brand.edit',
        'uses' => 'CatalogBrandController@edit',
        'middleware' => 'can:admin.catalog_brand.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.catalog_brand.update',
        'uses' => 'CatalogBrandController@update',
        'middleware' => 'can:admin.catalog_brand.edit',
    ])->where('id', '[0-9]+');

    Route::post('/approve/{id}', [
        'as' => 'admin.catalog_brand.approve',
        'uses' => 'CatalogBrandController@approve',
        'middleware' => 'can:admin.catalog_brand.approve',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.catalog_brand.update_status',
        'uses' => 'CatalogBrandController@updateStatus',
        'middleware' => 'can:admin.catalog_brand.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.catalog_brand.delete',
        'uses' => 'CatalogBrandController@delete',
        'middleware' => 'can:admin.catalog_brand.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.catalog_brand.mass_delete',
        'uses' => 'CatalogBrandController@massDelete',
        'middleware' => 'can:admin.catalog_brand.mass_delete',
    ]);
});

Route::prefix('catalog/size-chart')->group(function () {
    Route::get('/', [
        'as' => 'admin.catalog_size_chart.index',
        'uses' => 'CatalogSizeChartController@index',
        'middleware' => 'can:admin.catalog_size_chart.index',
    ]);

    Route::post('/filters', [
        'as' => 'admin.catalog_size_chart.filters',
        'uses' => 'CatalogSizeChartController@filters',
        'middleware' => 'can:admin.catalog_size_chart.filters',
    ]);

    Route::get('/create', [
        'as' => 'admin.catalog_size_chart.create',
        'uses' => 'CatalogSizeChartController@create',
        'middleware' => 'can:admin.catalog_size_chart.create',
    ]);

    Route::post('/', [
        'as' => 'admin.catalog_size_chart.store',
        'uses' => 'CatalogSizeChartController@store',
        'middleware' => 'can:admin.catalog_size_chart.create',
    ]);

    Route::get('/edit/{id}', [
        'as' => 'admin.catalog_size_chart.edit',
        'uses' => 'CatalogSizeChartController@edit',
        'middleware' => 'can:admin.catalog_size_chart.edit',
    ])->where('id', '[0-9]+');

    Route::put('/{id}', [
        'as' => 'admin.catalog_size_chart.update',
        'uses' => 'CatalogSizeChartController@update',
        'middleware' => 'can:admin.catalog_size_chart.edit',
    ])->where('id', '[0-9]+');

    Route::post('/update_status', [
        'as' => 'admin.catalog_size_chart.update_status',
        'uses' => 'CatalogSizeChartController@updateStatus',
        'middleware' => 'can:admin.catalog_size_chart.update_status',
    ]);

    Route::delete('/delete/{id}', [
        'as' => 'admin.catalog_size_chart.delete',
        'uses' => 'CatalogSizeChartController@delete',
        'middleware' => 'can:admin.catalog_size_chart.delete',
    ])->where('id', '[0-9]+');

    Route::delete('/massDelete', [
        'as' => 'admin.catalog_size_chart.mass_delete',
        'uses' => 'CatalogSizeChartController@massDelete',
        'middleware' => 'can:admin.catalog_size_chart.mass_delete',
    ]);
});
