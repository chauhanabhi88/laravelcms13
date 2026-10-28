<?php

return [
    'name' => 'Cart',

    /*
    | Session filter / cache entity key of the admin grid.
    */
    'cache' => [
        'cart' => 'cart',
    ],

    /*
    | Guest cart cookie (httpOnly, encrypted by the web middleware). API clients send the same
    | token in the X-Cart-Token header.
    */
    'cookie' => 'cart_token',
    'cookie_days' => 30,
    'api_header' => 'X-Cart-Token',

    /*
    | Defaults for admin > Settings > Cart (read through Modules\Cart\Support\CartSettings).
    | free_delivery: always | threshold (free from free_delivery_min, else delivery_fee per
    | order) | per_seller_group (delivery_fee per seller group below free_delivery_min).
    */
    'defaults' => [
        'max_qty_per_line' => 10,
        'max_lines' => 50,
        'free_delivery' => 'always',
        'free_delivery_min' => '0',
        'delivery_fee' => '0',
        'cod_fee' => '0',
        'abandoned_cart_enabled' => 2,
    ],

    'free_delivery_modes' => ['always', 'threshold', 'per_seller_group'],

    /*
    | Buy Now carts expire after this many minutes; idle guest carts after cookie_days.
    */
    'buy_now_ttl_minutes' => 60,

    /*
    | Abandoned-cart reminders go to customer carts idle between these many hours.
    */
    'abandoned' => [
        'after_hours' => 3,
        'within_hours' => 24,
    ],

    /*
    | The cart page lists at most this many saved-for-later lines.
    */
    'saved_for_later_limit' => 50,
];
