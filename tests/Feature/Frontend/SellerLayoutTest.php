<?php

use Modules\Theme\Http\Controllers\StyleguideController;

test('the seller layout renders the sidebar, topbar and page content', function () {
    $html = app(StyleguideController::class)->seller()->render();

    expect($html)
        ->toContain('Supplier panel navigation')
        ->toContain('Dashboard preview')
        ->toContain("overlay({ name: 'seller-menu' })")
        ->toContain('Orders today');
});

test('seller menu items render only once their routes exist', function () {
    config(['theme.seller_menu' => [
        'catalog' => ['label' => 'Catalog', 'items' => [
            ['route' => 'customer.myaccount', 'label' => 'Existing route', 'icon' => 'package'],
            ['route' => 'seller.products.index', 'label' => 'Future route', 'icon' => 'package'],
        ]],
    ]]);

    $html = view('theme::styleguide.seller')->render();

    expect($html)
        ->toContain('Existing route')
        ->not->toContain('Future route');
});

test('the seller auth layout renders the marketing panel', function () {
    $html = view('theme::layouts.seller.auth')->render();

    expect($html)->toContain('zero commission on every sale');
});
