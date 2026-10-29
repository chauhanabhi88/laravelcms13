<?php

return [
    'name' => 'Promotion',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'coupon' => 'Coupon',
        'flash_deal' => 'FlashDeal',
    ],

    /*
    | Coupon shapes. Codes are upper case letters, digits and dashes.
    */
    'code_pattern' => '/^[A-Z0-9][A-Z0-9-]{2,29}$/',
    'types' => ['percent', 'flat', 'free_delivery'],
    'scopes' => ['all', 'category', 'seller', 'product', 'brand'],
    'seller_scopes' => ['seller', 'product'],
    'funding' => ['platform', 'seller'],

    /*
    | Channels a coupon can be limited to: web = the storefront, app = API clients (android/ios).
    */
    'channels' => ['web', 'app'],
    'payment_methods' => ['prepaid', 'cod'],

    /*
    | Bulk generation of unique codes (admin): at most max_count per run, random part length.
    */
    'bulk' => [
        'max_count' => 1000,
        'random_length' => 8,
    ],

    /*
    | "Available offers" list in the cart and checkout.
    */
    'offers_limit' => 20,

    /*
    | Flash deal items edited per form save (empty rows shown for new items).
    */
    'flash_deal_new_rows' => 5,
    'flash_deal_max_items' => 200,

    /*
    | Home "flash_deal" section: most cards shown.
    */
    'flash_deal_section_limit' => 12,

    /*
    | Referral share link cookie.
    */
    'referral_cookie' => 'referral_code',
    'referral_cookie_days' => 30,
    'referral_code_length' => 8,

    'seller_per_page' => 20,

    /*
    | Defaults for admin > Settings > Promotions (read through Modules\Promotion\Support\PromotionSettings).
    */
    'defaults' => [
        'allow_stacking' => 2,
        'max_coupon_discount_percent_of_cart' => 90,
        'first_order_prepaid_only' => 2,
        'referral_enabled' => 2,
        'referral_reward_amount' => 100,
        'referral_reward_min_cart' => 299,
        'referral_reward_validity_days' => 30,
    ],
];
