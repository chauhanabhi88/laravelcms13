<?php

return [
    'settings' => [
        'group' => 'Logistics',
        'default_courier' => [
            'label' => 'Default courier (code)',
            'comment' => 'Courier code used when no courier rule matches, and for the live delivery estimate (e.g. shiprocket, or manual while the API is down).',
        ],
        'default_strategy' => [
            'label' => 'Default carrier strategy',
            'comment' => 'How the carrier is chosen when no rule matches.',
        ],
        'live_serviceability' => [
            'label' => 'Live delivery estimate',
            'comment' => 'Ask the default courier for serviceability and EDD on the product page, cart and checkout (falls back to the pincode matrix).',
        ],
        'auto_rto_after_attempts' => [
            'label' => 'Auto return after failed attempts',
            'comment' => 'Failed delivery attempts after which the package is sent back to the seller automatically (0 = never).',
        ],
        'poll_interval_hours' => [
            'label' => 'Tracking poll interval (hours)',
            'comment' => 'How often the tracking poll (webhook fallback) is expected to run.',
        ],
        'serviceability_cache_hours' => [
            'label' => 'Serviceability cache (hours)',
            'comment' => 'How long courier serviceability answers are reused.',
        ],
        'estimator_timeout_ms' => [
            'label' => 'Estimate timeout (ms)',
            'comment' => 'The storefront estimate falls back to the pincode matrix when the courier takes longer.',
        ],
        'rto_charge_percent' => [
            'label' => 'RTO freight (% of forward)',
            'comment' => 'Return-to-origin freight recorded per shipment, as a percentage of the forward freight.',
        ],
    ],

    'errors' => [
        'not_configured' => 'The courier API credentials are not configured.',
        'unreachable' => 'The courier API could not be reached. Please try again.',
        'login_failed' => 'The courier API refused the credentials.',
        'provider' => 'Courier API error: :message',
        'unknown_driver' => 'Unknown courier driver ":driver".',
        'no_shipping_address' => 'The order has no shipping address.',
        'no_pickup_location' => 'The seller warehouse is not registered as a pickup location yet.',
        'not_created' => 'The shipment is not registered with the courier yet.',
        'not_booked' => 'The shipment has no AWB yet.',
    ],
];
