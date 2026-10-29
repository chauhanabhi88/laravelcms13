<?php

return [
    'messages' => [
        'too_many_requests' => 'Too many requests. Please slow down and try again shortly.',
        'device_revoked' => 'The device has been signed out.',
        'device_not_found' => 'Device not found.',
    ],

    // Default messages of the app-specific error codes (config/api_codes.php).
    'codes' => [
        'token_invalid' => 'Your session has expired. Please log in again.',
        'token_revoked' => 'This device has been signed out. Please log in again.',
        'seller_suspended' => 'This seller is not accepting orders right now.',
        'price_changed' => 'The price changed since you last looked. Please review the new total.',
        'out_of_stock' => 'Sorry, some items are out of stock.',
        'cod_not_eligible' => 'Cash on Delivery is not available for this order.',
        'otp_rate_limited' => 'Too many OTP requests. Please wait a while before trying again.',
    ],
];
