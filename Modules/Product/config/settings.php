<?php

/*
| Catalog moderation settings (admin > Settings > Product). Read through
| Modules\Product\Support\ProductSettings, never with settings() directly.
*/

return [
    'product::product.settings.product' => [
        'auto_approve_trusted_sellers' => [
            'label' => 'product::product.settings.auto_approve_trusted_sellers.label',
            'type' => 'select',
            'options' => [
                2 => 'No',
                1 => 'Yes',
            ],
            'storage' => 'db',
            'comment' => 'product::product.settings.auto_approve_trusted_sellers.comment',
        ],
        'trusted_seller_min_rating' => [
            'label' => 'product::product.settings.trusted_seller_min_rating.label',
            'type' => 'number',
            'placeholder' => '4.3',
            'storage' => 'db',
            'comment' => 'product::product.settings.trusted_seller_min_rating.comment',
            'min' => 0,
            'max' => 5,
        ],
        'qc_sla_hours' => [
            'label' => 'product::product.settings.qc_sla_hours.label',
            'type' => 'number',
            'placeholder' => '72',
            'storage' => 'db',
            'comment' => 'product::product.settings.qc_sla_hours.comment',
            'min' => 1,
            'max' => 720,
        ],
    ],
];
