<?php

return [
    'name' => 'Payment',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'payment' => 'Payment',
        'payment_refund' => 'PaymentRefund',
        'webhook_event' => 'WebhookEvent',
        'payment_recon' => 'PaymentRecon',
    ],

    /*
    | Prepaid gateway driver used when checkout asks for "prepaid": razorpay in production,
    | fake for local work and tests (offline, see Drivers\FakeGateway).
    */
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'razorpay'),

    'gateways' => ['razorpay', 'fake'],

    /*
    | Razorpay Standard Checkout. Keys stay in .env. Auto-capture must be enabled on the
    | Razorpay account (plan M11 §12).
    */
    'razorpay' => [
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com'),
        'checkout_js' => 'https://checkout.razorpay.com/v1/checkout.js',
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'theme_color' => '#0F766E',
    ],

    /*
    | Fake driver: callbacks are signed with this secret so the real verification path runs.
    */
    'fake' => [
        'key_id' => 'rzp_test_fake',
        'key_secret' => 'fake_secret',
        'webhook_secret' => 'fake_webhook_secret',
    ],

    /*
    | Queue for ProcessWebhookEvent. Run a worker with --queue=webhooks,notifications,default.
    */
    'webhook_queue' => env('PAYMENT_WEBHOOK_QUEUE', 'webhooks'),

    /*
    | OTP purpose for COD confirmation (Core OtpService).
    */
    'cod_otp_purpose' => 'cod_confirm',

    /*
    | Defaults for admin > Settings > Payment (read through Modules\Payment\Support\PaymentSettings).
    */
    'defaults' => [
        'cod_enabled' => 1,
        'cod_min_value' => '0',
        'cod_max_value' => '5000',
        'cod_daily_limit' => 3,
        'cod_otp_threshold' => '2000',
        'cod_otp_new_customers' => 1,
        'payment_window_minutes' => 30,
        'cod_otp_window_minutes' => 15,
    ],

    /*
    | Refund modes: source (prepaid, back to the instrument), bank / upi (COD, via payout).
    */
    'refund_modes' => ['source', 'bank', 'upi'],
];
