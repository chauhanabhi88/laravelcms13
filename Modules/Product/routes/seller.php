<?php

/*
|--------------------------------------------------------------------------
| Supplier panel catalog routes (M05)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/..., names "seller.*", middleware
| web + auth:seller + seller.active. Catalog staff and owners only (seller.can:catalog).
| Controllers read {product} / {upload} / {variant} with $request->route() because the
| optional {locale} prefix shifts positional arguments.
|
*/

Route::prefix('catalog')->middleware('seller.can:catalog')->group(function () {
    // Products
    Route::get('/products', [
        'as' => 'product.index',
        'uses' => 'SellerProductController@index',
    ]);
    Route::get('/products/filters', [
        'as' => 'product.filters',
        'uses' => 'SellerProductController@filters',
    ]);
    Route::get('/products/{product}/edit', [
        'as' => 'product.edit',
        'uses' => 'SellerProductController@edit',
    ])->where('product', '[0-9]+');
    Route::post('/products/{product}/pause', [
        'as' => 'product.pause',
        'uses' => 'SellerProductController@pause',
    ])->where('product', '[0-9]+');
    Route::post('/products/{product}/resume', [
        'as' => 'product.resume',
        'uses' => 'SellerProductController@resume',
    ])->where('product', '[0-9]+');
    Route::delete('/products/{product}', [
        'as' => 'product.delete',
        'uses' => 'SellerProductController@destroy',
    ])->where('product', '[0-9]+');

    // Images (wizard step 4)
    Route::post('/products/{product}/images', [
        'as' => 'image.upload',
        'uses' => 'ImageUploadController@store',
    ])->where('product', '[0-9]+');
    Route::delete('/products/{product}/images', [
        'as' => 'image.delete',
        'uses' => 'ImageUploadController@destroy',
    ])->where('product', '[0-9]+');
    Route::post('/products/{product}/images/reorder', [
        'as' => 'image.reorder',
        'uses' => 'ImageUploadController@reorder',
    ])->where('product', '[0-9]+');

    // Single listing wizard
    Route::get('/add', [
        'as' => 'listing.create',
        'uses' => 'ListingWizardController@create',
    ]);
    Route::post('/add', [
        'as' => 'listing.category',
        'uses' => 'ListingWizardController@chooseCategory',
    ]);
    Route::get('/add/details', [
        'as' => 'listing.details',
        'uses' => 'ListingWizardController@details',
    ]);
    Route::post('/add/details', [
        'as' => 'listing.store',
        'uses' => 'ListingWizardController@store',
    ]);
    Route::post('/add/{product}/variants', [
        'as' => 'listing.variants.save',
        'uses' => 'ListingWizardController@saveVariants',
    ])->where('product', '[0-9]+');
    Route::post('/add/{product}/submit', [
        'as' => 'listing.submit',
        'uses' => 'ListingWizardController@submit',
    ])->where('product', '[0-9]+');
    Route::get('/add/{product}/{step}', [
        'as' => 'listing.step',
        'uses' => 'ListingWizardController@step',
    ])->where(['product' => '[0-9]+', 'step' => 'details|variants|images|pricing|review']);
    Route::post('/add/{product}/{step}', [
        'as' => 'listing.save',
        'uses' => 'ListingWizardController@save',
    ])->where(['product' => '[0-9]+', 'step' => 'details|pricing']);
    Route::get('/sku-check', [
        'as' => 'listing.sku_check',
        'uses' => 'ListingWizardController@skuCheck',
    ]);
    Route::get('/category-search', [
        'as' => 'category.search',
        'uses' => 'ListingWizardController@categorySearch',
    ]);

    // Bulk catalog upload + history
    Route::get('/uploads', [
        'as' => 'upload.index',
        'uses' => 'BulkUploadController@index',
    ]);
    Route::get('/uploads/create', [
        'as' => 'upload.create',
        'uses' => 'BulkUploadController@create',
    ]);
    Route::get('/uploads/template', [
        'as' => 'upload.template',
        'uses' => 'BulkUploadController@template',
    ]);
    Route::post('/uploads', [
        'as' => 'upload.store',
        'uses' => 'BulkUploadController@store',
    ]);
    Route::get('/uploads/{upload}', [
        'as' => 'upload.show',
        'uses' => 'BulkUploadController@show',
    ])->where('upload', '[0-9]+');
    Route::get('/uploads/{upload}/status', [
        'as' => 'upload.status',
        'uses' => 'BulkUploadController@status',
    ])->where('upload', '[0-9]+');
    Route::get('/uploads/{upload}/error-report', [
        'as' => 'upload.error_report',
        'uses' => 'BulkUploadController@errorReport',
    ])->where('upload', '[0-9]+');
    Route::get('/uploads/{upload}/file', [
        'as' => 'upload.file',
        'uses' => 'BulkUploadController@file',
    ])->where('upload', '[0-9]+');

    // Bulk price & stock
    Route::get('/price-stock', [
        'as' => 'price_stock.create',
        'uses' => 'PriceStockUploadController@create',
    ]);
    Route::get('/price-stock/template', [
        'as' => 'price_stock.template',
        'uses' => 'PriceStockUploadController@template',
    ]);
    Route::post('/price-stock', [
        'as' => 'price_stock.store',
        'uses' => 'PriceStockUploadController@store',
    ]);

    // Inventory
    Route::get('/inventory', [
        'as' => 'inventory.index',
        'uses' => 'InventoryController@index',
    ]);
    Route::get('/inventory/filters', [
        'as' => 'inventory.filters',
        'uses' => 'InventoryController@filters',
    ]);
    Route::get('/inventory/export', [
        'as' => 'inventory.export',
        'uses' => 'InventoryController@export',
    ]);
    Route::patch('/inventory/{variant}', [
        'as' => 'inventory.update',
        'uses' => 'InventoryController@update',
    ])->where('variant', '[0-9]+');

    // Price & earnings calculator
    Route::get('/calculator', [
        'as' => 'calculator',
        'uses' => 'CalculatorController@index',
    ]);
    Route::post('/calculator', [
        'as' => 'calculator.calculate',
        'uses' => 'CalculatorController@calculate',
    ]);
});
