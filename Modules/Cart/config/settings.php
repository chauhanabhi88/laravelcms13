<?php

/*
| Cart settings (admin > Settings > Cart). Read through Modules\Cart\Support\CartSettings,
| never with settings() directly; blank values fall back to config('cart.defaults').
*/

return [
    'cart::cart.settings.group' => [
        'max_qty_per_line' => [
            'label' => 'cart::cart.settings.max_qty_per_line.label',
            'type' => 'text',
            'placeholder' => '10',
            'storage' => 'db',
            'comment' => 'cart::cart.settings.max_qty_per_line.comment',
        ],
        'max_lines' => [
            'label' => 'cart::cart.settings.max_lines.label',
            'type' => 'text',
            'placeholder' => '50',
            'storage' => 'db',
            'comment' => 'cart::cart.settings.max_lines.comment',
        ],
        'free_delivery' => [
            'label' => 'cart::cart.settings.free_delivery.label',
            'type' => 'select',
            'options' => [
                'always' => 'Always free',
                'threshold' => 'Free above a minimum order value',
                'per_seller_group' => 'Flat fee per seller package',
            ],
            'storage' => 'db',
            'comment' => 'cart::cart.settings.free_delivery.comment',
        ],
        'free_delivery_min' => [
            'label' => 'cart::cart.settings.free_delivery_min.label',
            'type' => 'text',
            'placeholder' => '0',
            'storage' => 'db',
            'comment' => 'cart::cart.settings.free_delivery_min.comment',
        ],
        'delivery_fee' => [
            'label' => 'cart::cart.settings.delivery_fee.label',
            'type' => 'text',
            'placeholder' => '0',
            'storage' => 'db',
            'comment' => 'cart::cart.settings.delivery_fee.comment',
        ],
        'cod_fee' => [
            'label' => 'cart::cart.settings.cod_fee.label',
            'type' => 'text',
            'placeholder' => '0',
            'storage' => 'db',
            'comment' => 'cart::cart.settings.cod_fee.comment',
        ],
        'abandoned_cart_enabled' => [
            'label' => 'cart::cart.settings.abandoned_cart_enabled.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'cart::cart.settings.abandoned_cart_enabled.comment',
        ],
    ],
];
