<?php

/*
|--------------------------------------------------------------------------
| Supplier panel manifest routes (M14)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/..., names "seller.*", middleware
| web + auth:seller + seller.active. Pickup batches (manifests) of packed orders; order
| staff and owners only (seller.can:orders). The order desk itself is in the Order module.
|
*/

Route::prefix('orders/manifests')->middleware('seller.can:orders')->group(function () {
    Route::get('/', [
        'as' => 'manifest.index',
        'uses' => 'ManifestController@index',
    ]);
    Route::post('/', [
        'as' => 'manifest.create',
        'uses' => 'ManifestController@store',
    ]);
    Route::get('/{id}', [
        'as' => 'manifest.download',
        'uses' => 'ManifestController@download',
    ])->where('id', '[0-9]+');
});
