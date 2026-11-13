<?php

/*
|--------------------------------------------------------------------------
| SEO routes (plan M21 E1/E2)
|--------------------------------------------------------------------------
|
| Crawler files at the site root: no {locale} prefix, no session or cookies. public/robots.txt
| must not exist, or the web server answers /robots.txt before Laravel does.
|
*/

Route::get('/robots.txt', [
    'as' => 'storefront.robots',
    'uses' => 'SitemapController@robots',
]);

Route::get('/sitemap.xml', [
    'as' => 'storefront.sitemap',
    'uses' => 'SitemapController@index',
]);

Route::get('/sitemaps/{file}', [
    'as' => 'storefront.sitemap.file',
    'uses' => 'SitemapController@show',
])->where('file', 'sitemap-[a-z]+-[0-9]+\.xml');
