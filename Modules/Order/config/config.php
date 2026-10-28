<?php

return [
    'name' => 'Order',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'sales_order' => 'SalesOrder',
        'seller_order' => 'SellerOrder',
    ],

    /*
    | Session key prefix of the web checkout state (CheckoutSession, stored as a plain array).
    */
    'checkout_session_key' => 'checkout',

    /*
    | Retries of the place-order transaction when MySQL reports a deadlock.
    */
    'place_order_attempts' => 3,

    /*
    | Channels a sales order can be placed from (sales_order.channel).
    */
    'channels' => ['web', 'android', 'ios'],

    /*
    | Orders still waiting for payment (or a COD OTP) this many minutes after the payment
    | window should have closed are cancelled by order:cancel-abandoned, a safety net for a
    | missed PaymentFailed event.
    */
    'abandoned_grace_minutes' => 30,

    /*
    | Customer cancellation reasons (lang order::front.cancel.reasons.*).
    */
    'cancel_reasons' => ['changed_mind', 'ordered_by_mistake', 'better_price', 'delivery_too_late', 'other'],

    /*
    | Defaults for admin > Settings > Order (read through Modules\Order\Support\OrderSettings).
    */
    'defaults' => [
        'rto_block_threshold' => 3,
        'rto_window_days' => 90,
        'max_open_cod_orders' => 3,
        'reseller_max_markup_multiplier' => '1.5',
        'allow_customer_cancel_after_packed' => 2,
    ],
];
