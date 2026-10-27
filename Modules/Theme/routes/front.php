<?php

/*
|--------------------------------------------------------------------------
| Front Routes
|--------------------------------------------------------------------------
|
| Component styleguide for visual QA. Registered only in the local
| environment; elsewhere /styleguide falls through to the CMS page
| catch-all and returns 404.
|
*/

if (app()->isLocal()) {
    Route::get('/styleguide', [
        'as' => 'theme.styleguide',
        'uses' => 'StyleguideController@index',
    ]);

    Route::get('/styleguide/seller', [
        'as' => 'theme.styleguide.seller',
        'uses' => 'StyleguideController@seller',
    ]);
}
