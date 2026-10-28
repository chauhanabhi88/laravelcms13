<?php

return [
    'name' => 'Order',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'sales_order' => 'SalesOrder',
        'seller_order' => 'SellerOrder',
        'seller_order_desk' => 'SellerOrderDesk',
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
        'label_format' => 'a4_4up',
        'sla_grace_hours' => 12,
        'auto_confirm_return_days' => 7,
        'reseller_white_label' => 1,
        'include_own_invoice' => 1,
    ],

    /*
    | Supplier order desk (plan M14).
    | - seller_per_page: rows per page; batch_limit: orders per accept / label / manifest action
    | - sla_warning_percent: share of the dispatch window after which the seller is warned once
    | - sla_at_risk_hours: the desk's amber chip below this many hours left
    | - counts_ttl: seconds the per-seller tab counts stay cached (flushed on status changes)
    | - booking_backoff: seconds between AWB booking retries after acceptance (then alert)
    | - documents_disk / documents_folder: private storage of slips, invoices and label files
    | - seller_cancel_reasons / receipt_conditions: lang order::fulfilment.*
    */
    'fulfilment' => [
        'seller_per_page' => 25,
        'batch_limit' => 100,
        'sla_warning_percent' => 75,
        'sla_at_risk_hours' => 12,
        'counts_ttl' => 60,
        'booking_backoff' => [300, 900, 1800],
        'documents_disk' => 'private',
        'documents_folder' => 'order-documents',
        'seller_cancel_reasons' => ['out_of_stock', 'price_error', 'cannot_fulfil_in_time', 'other'],
        'receipt_conditions' => ['good', 'damaged', 'wrong_item'],
        'claim_max_photos' => 5,
    ],
];
