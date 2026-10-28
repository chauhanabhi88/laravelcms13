<?php

return [
    'titles' => [
        'webhooks' => 'Webhooks',
        'list' => 'Webhook log',
        'show' => 'Webhook #:id',
        'payload' => 'Payload',
    ],

    'labels' => [
        'list' => 'View webhook log',
        'filters' => 'Filter webhook log',
        'show' => 'View webhook',
        'replay' => 'Replay webhook',
        'back' => 'Back',
        'provider' => 'Provider',
        'status' => 'Status',
        'search' => 'Event id or event type',
        'event_type' => 'Event',
        'event_id' => 'Event id',
        'signature' => 'Signature',
        'valid' => 'Valid',
        'invalid' => 'Invalid',
        'attempts' => 'Attempts',
        'error' => 'Error',
        'received_at' => 'Received',
        'processed_at' => 'Processed',
        'confirm_replay' => 'Process this webhook again?',
        'no_payload' => 'No payload is kept for requests with an invalid signature.',
    ],

    'columns' => [
        'id' => 'ID',
        'provider' => 'Provider',
        'event_type' => 'Event',
        'event_id' => 'Event ID',
        'signature_valid' => 'Signature',
        'status' => 'Status',
        'attempts' => 'Attempts',
        'error' => 'Error',
        'created_at' => 'Received At',
        'processed_at' => 'Processed At',
    ],

    'statuses' => [
        'received' => 'Received',
        'processed' => 'Processed',
        'failed' => 'Failed',
        'ignored' => 'Ignored',
    ],

    'providers' => [
        'razorpay' => 'Razorpay',
        'razorpayx' => 'RazorpayX',
        'shiprocket' => 'Shiprocket',
    ],

    'messages' => [
        'not_found' => 'The webhook was not found.',
        'not_replayable' => 'Only signed Razorpay webhooks can be replayed.',
        'replayed' => 'The webhook was processed again: :status.',
    ],
];
