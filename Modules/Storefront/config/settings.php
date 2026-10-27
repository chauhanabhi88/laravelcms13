<?php

/*
| Storefront settings (admin > Settings > Storefront). Read through
| Modules\Storefront\Support\StorefrontSettings, never with settings() directly.
*/

return [
    'storefront::storefront.settings.storefront' => [
        'show_out_of_stock' => [
            'label' => 'storefront::storefront.settings.show_out_of_stock.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.show_out_of_stock.comment',
        ],
        'free_delivery_badge' => [
            'label' => 'storefront::storefront.settings.free_delivery_badge.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.free_delivery_badge.comment',
        ],
        'trending_terms_manual' => [
            'label' => 'storefront::storefront.settings.trending_terms_manual.label',
            'type' => 'text',
            'placeholder' => 'kurti, saree, earbuds',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.trending_terms_manual.comment',
        ],
    ],
];
