<?php

return [
    'name' => 'Logistics',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'courier' => 'Courier',
        'courier_rule' => 'CourierRule',
        'shipment' => 'Shipment',
        'ndr_case' => 'NdrCase',
        'pickup_batch' => 'PickupBatch',
    ],

    /*
    | Courier drivers (courier.driver). "fake" is for tests and local work (offline).
    */
    'drivers' => ['shiprocket', 'manual', 'fake'],

    /*
    | Assignment strategies of a courier rule (plan M13 §5.2).
    */
    'strategies' => ['recommended', 'cheapest', 'fastest', 'preferred_list'],

    /*
    | Pincode zones a rule may target (directory_pincode.zone).
    */
    'zones' => ['metro', 'roi', 'ne_jk', 'special'],

    /*
    | Shiprocket (plan M13 §5.3, §8). Credentials of the API user stay in .env; a courier row
    | may override them in its encrypted credentials. The webhook token is the value
    | configured in the Shiprocket dashboard and sent back in the x-api-key header.
    */
    'shiprocket' => [
        'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in'),
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'webhook_token' => env('SHIPROCKET_WEBHOOK_TOKEN'),
        'token_cache_days' => 9,
        'country' => 'India',

        /*
        | Shiprocket status (label, upper-case) → our shipment status. Unknown labels are
        | logged and kept as scans without a transition (plan M13 §5.4, §13).
        */
        'status_map' => [
            'AWB ASSIGNED' => 'awb_assigned',
            'LABEL GENERATED' => 'label_generated',
            'MANIFEST GENERATED' => 'label_generated',
            'PICKUP SCHEDULED' => 'pickup_scheduled',
            'PICKUP GENERATED' => 'pickup_scheduled',
            'PICKUP QUEUED' => 'pickup_scheduled',
            'OUT FOR PICKUP' => 'pickup_scheduled',
            'PICKUP RESCHEDULED' => 'pickup_scheduled',
            'PICKED UP' => 'picked_up',
            'SHIPPED' => 'in_transit',
            'IN TRANSIT' => 'in_transit',
            'REACHED AT DESTINATION HUB' => 'in_transit',
            'REACHED DESTINATION HUB' => 'in_transit',
            'MISROUTED' => 'in_transit',
            'DELAYED' => 'in_transit',
            'OUT FOR DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'UNDELIVERED' => 'ndr',
            'RTO INITIATED' => 'rto_initiated',
            'RTO ACKNOWLEDGED' => 'rto_initiated',
            'RTO IN TRANSIT' => 'rto_in_transit',
            'RTO OFD' => 'rto_in_transit',
            'RTO NDR' => 'rto_in_transit',
            'RTO DELIVERED' => 'rto_delivered',
            'LOST' => 'lost',
            'DAMAGED' => 'lost',
            'DESTROYED' => 'lost',
            'CANCELED' => 'cancelled',
            'CANCELLED' => 'cancelled',
        ],

        /*
        | Shiprocket shipment_status_id → label, used when a payload carries only the id.
        | Verify against the account's status list (they change; plan M13 §13).
        */
        'status_ids' => [
            6 => 'SHIPPED',
            7 => 'DELIVERED',
            8 => 'CANCELED',
            9 => 'RTO INITIATED',
            10 => 'RTO DELIVERED',
            12 => 'LOST',
            15 => 'PICKUP RESCHEDULED',
            17 => 'OUT FOR DELIVERY',
            18 => 'IN TRANSIT',
            19 => 'OUT FOR PICKUP',
            21 => 'UNDELIVERED',
            22 => 'DELAYED',
            38 => 'REACHED AT DESTINATION HUB',
            42 => 'PICKED UP',
        ],
    ],

    /*
    | Queue of ProcessShiprocketWebhook (shared with the payment webhooks).
    */
    'webhook_queue' => env('LOGISTICS_WEBHOOK_QUEUE', 'webhooks'),

    /*
    | Fallback package when the variants carry no weight/dimensions.
    */
    'package' => [
        'min_weight_grams' => 100,
        'default_length_cm' => 10,
        'default_breadth_cm' => 10,
        'default_height_cm' => 5,
    ],

    /*
    | Serviceability weight bucket (grams) for the cache key and the PDP estimate.
    */
    'weight_bucket_grams' => 500,

    /*
    | Polling fallback: shipments not synced for this many hours are polled (plan M13 §5.4).
    */
    'poll_stale_hours' => 4,
    'poll_batch_size' => 100,

    /*
    | A shipment in transit for longer than this many days counts as "stuck" in the grid filter.
    */
    'stuck_after_days' => 3,

    /*
    | NDR actions (ndr_case.customer_action) and reasons an admin can record.
    */
    'ndr_actions' => ['reattempt', 'change_address', 'cancel'],

    /*
    | Defaults for admin > Settings > Logistics (read through Modules\Logistics\Support\LogisticsSettings).
    */
    'defaults' => [
        'default_courier' => env('LOGISTICS_DEFAULT_COURIER', 'shiprocket'),
        'default_strategy' => 'recommended',
        'live_serviceability' => 1,
        'auto_rto_after_attempts' => 3,
        'poll_interval_hours' => 2,
        'serviceability_cache_hours' => 24,
        'estimator_timeout_ms' => 1500,
        'rto_charge_percent' => 100,
    ],
];
