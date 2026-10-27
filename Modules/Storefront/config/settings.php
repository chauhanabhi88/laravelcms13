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
        'transit_days' => [
            'label' => 'storefront::storefront.settings.transit_days.label',
            'type' => 'text',
            'placeholder' => '{"same_city":2,"same_state":3,"metro_metro":4,"roi":5,"special":8}',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.transit_days.comment',
        ],
        'metro_city_ids' => [
            'label' => 'storefront::storefront.settings.metro_city_ids.label',
            'type' => 'text',
            'placeholder' => '12, 34, 56',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.metro_city_ids.comment',
        ],
        'special_zone_state_ids' => [
            'label' => 'storefront::storefront.settings.special_zone_state_ids.label',
            'type' => 'text',
            'placeholder' => '3, 4, 14',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.special_zone_state_ids.comment',
        ],
        'low_stock_display_threshold' => [
            'label' => 'storefront::storefront.settings.low_stock_display_threshold.label',
            'type' => 'text',
            'placeholder' => '5',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.low_stock_display_threshold.comment',
        ],
        'similar_price_band_percent' => [
            'label' => 'storefront::storefront.settings.similar_price_band_percent.label',
            'type' => 'text',
            'placeholder' => '30',
            'storage' => 'db',
            'comment' => 'storefront::storefront.settings.similar_price_band_percent.comment',
        ],
    ],
];
