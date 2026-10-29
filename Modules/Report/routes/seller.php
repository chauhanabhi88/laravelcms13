<?php

/*
|--------------------------------------------------------------------------
| Supplier analytics (M19, FR-RPT-006)
|--------------------------------------------------------------------------
|
| Mapped by Core's MapsSellerRoutes: /supplier/analytics, names "seller.*", middleware
| web + auth:seller + seller.active. Owners, order and finance staff.
|
*/

Route::middleware('seller.can:orders,finance')->get('analytics', [
    'as' => 'analytics.index',
    'uses' => 'SellerAnalyticsController@index',
]);
