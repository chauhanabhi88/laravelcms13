<?php

return [
    'name' => 'Returns',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'return_request' => 'ReturnRequest',
        'return_claim' => 'ReturnClaim',
    ],

    /*
    | Return reasons (lang returns::returns.reasons.*), in the order the customer sees them.
    | seller_fault_reasons: the delivery share is refunded and the seller bears the return.
    | photo_required_reasons: 1–5 photos are required (optional for the others).
    */
    'reasons' => ['wrong_size', 'defective', 'damaged', 'not_as_described', 'wrong_item', 'missing_item', 'quality', 'changed_mind'],
    'seller_fault_reasons' => ['defective', 'damaged', 'wrong_item', 'missing_item', 'not_as_described'],
    'photo_required_reasons' => ['defective', 'damaged', 'wrong_item', 'not_as_described'],

    /*
    | Never approved automatically (a CX agent looks at it first).
    */
    'review_reasons' => ['changed_mind'],

    /*
    | Seller claim reasons (lang returns::return_claim.reasons.*).
    */
    'claim_reasons' => ['damaged_return', 'wrong_item_returned', 'used_item', 'missing_parts'],

    /*
    | The customer's return rate looks back this many days (auto-approval, trusted customers).
    */
    'return_rate_days' => 90,

    /*
    | Session key of the customer's return wizard draft (ReturnDraft, a plain array).
    */
    'draft_session_key' => 'returns_draft',

    /*
    | Customer photos and seller claim photos: private disk, re-encoded, random names.
    */
    'uploads' => [
        'disk' => 'private',
        'folder' => 'returns',
        'max_size_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_dimension' => 2000,
    ],

    'customer_per_page' => 10,
    'seller_per_page' => 25,

    /*
    | Defaults for admin > Settings > Returns (read through Modules\Returns\Support\ReturnSettings).
    */
    'defaults' => [
        'auto_approve' => 1,
        'auto_approve_max_return_rate' => 30,
        'auto_approve_max_value' => 2000,
        'refund_on_pickup_for_trusted' => 1,
        'trusted_min_delivered_orders' => 3,
        'max_images' => 5,
        'claim_window_hours' => 48,
        'refund_delivery_fee_on_seller_fault' => 1,
    ],
];
