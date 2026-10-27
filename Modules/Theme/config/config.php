<?php

return [
    'name' => 'Theme',
    'sidebar' => 1,

    /*
    |--------------------------------------------------------------------------
    | Public site navigation
    |--------------------------------------------------------------------------
    |
    | Items in the frontend header. "route" is a named route, "label" a
    | translation key, "module" (optional) hides the item when that module
    | is disabled, and "active" is a route-name pattern for highlighting.
    |
    */
    'frontend_nav' => [
        ['route' => 'homepage', 'label' => 'theme::frontend.nav.home', 'active' => 'homepage'],
        ['route' => 'blog.index', 'label' => 'theme::frontend.nav.blog', 'module' => 'Blog', 'active' => 'blog.*'],
        ['route' => 'contact.index', 'label' => 'theme::frontend.nav.contact', 'module' => 'Contact', 'active' => 'contact.*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront header
    |--------------------------------------------------------------------------
    |
    | Named routes owned by later modules. Each link renders only once its route
    | exists (Route::has), so the header works before those modules ship.
    | "sell" falls back to the CMS page slug until the Seller landing exists.
    |
    */
    'header' => [
        'search_route' => 'storefront.search',
        'suggest_route' => 'storefront_api.autocomplete',
        'cart_route' => 'cart.index',
        'orders_route' => 'customer.orders.index',
        'wishlist_route' => 'customer.wishlist.index',
        'wishlist_toggle_route' => 'customer.wishlist.toggle',
        'account_route' => 'customer.myaccount',
        'sell' => ['route' => 'seller.landing', 'page' => 'sell-with-us'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mega-menu
    |--------------------------------------------------------------------------
    |
    | The tree comes from the bound MegaMenuProvider (Catalog, M02) and is cached
    | per locale; Modules\Theme\Support\MegaMenu::flush() clears it.
    |
    */
    'mega_menu' => [
        'depth' => 3,
        'cache_ttl' => 3600,
        'footer_limit' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer content
    |--------------------------------------------------------------------------
    |
    | "about_block" is a Block slug rendered in the first footer column.
    | "columns" are link groups. A link is a named "route" (shown when the route
    | exists and its optional "module" is enabled) and/or a CMS "page" slug
    | (shown when that page is published). The route wins when both resolve.
    |
    */
    'footer' => [
        'about_block' => 'footer_about',
        'columns' => [
            'help' => [
                'label' => 'theme::frontend.footer.help',
                'links' => [
                    ['page' => 'returns-policy', 'label' => 'theme::frontend.footer.links.returns'],
                    ['page' => 'shipping-policy', 'label' => 'theme::frontend.footer.links.shipping'],
                    ['page' => 'cancellation-policy', 'label' => 'theme::frontend.footer.links.cancellation'],
                    ['page' => 'faqs', 'label' => 'theme::frontend.footer.links.faqs'],
                    ['route' => 'contact.index', 'module' => 'Contact', 'label' => 'theme::frontend.footer.links.contact'],
                ],
            ],
            'company' => [
                'label' => 'theme::frontend.footer.company',
                'links' => [
                    ['page' => 'about-us', 'label' => 'theme::frontend.footer.links.about'],
                    ['page' => 'careers', 'label' => 'theme::frontend.footer.links.careers'],
                    ['route' => 'seller.landing', 'page' => 'sell-with-us', 'label' => 'theme::frontend.footer.links.sell'],
                    ['page' => 'grievance-officer', 'label' => 'theme::frontend.footer.links.grievance'],
                    ['route' => 'blog.index', 'module' => 'Blog', 'label' => 'theme::frontend.footer.links.blog'],
                ],
            ],
            'legal' => [
                'label' => 'theme::frontend.footer.legal',
                'links' => [
                    ['page' => 'privacy-policy', 'label' => 'theme::frontend.footer.links.privacy'],
                    ['page' => 'terms-and-conditions', 'label' => 'theme::frontend.footer.links.terms'],
                ],
            ],
        ],
    ],

    'frontend_logo' => 'modules/theme/frontend/images/logo.png',

    /* Browser UI colour (meta theme-color); keep in sync with --accent in resources/css/app.css. */
    'brand_color' => '#0F766E',

    /*
    |--------------------------------------------------------------------------
    | Settings defaults (see Modules\Theme\Support\ThemeSettings)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'show_download_app' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier panel sidebar
    |--------------------------------------------------------------------------
    |
    | Sections of the seller layout sidebar, filled by M03/M05/M14/M17:
    | 'catalog' => ['label' => 'seller::menu.catalog', 'items' => [
    |     ['route' => 'seller.products.index', 'label' => '…', 'icon' => 'package', 'active' => 'seller.products.*'],
    | ]].
    | Items whose route does not exist yet are skipped.
    |
    */
    'seller_menu' => [],
];
