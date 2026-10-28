<?php

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| /webhooks/{provider}: no session, no CSRF, no throttle; the controller verifies the
| provider's token before reading the payload. Shiprocket's dashboard may refuse a URL that
| contains its own name, so the same endpoint also answers at /webhooks/courier-tracking.
|
*/

Route::post('/shiprocket', [
    'as' => 'webhook.shiprocket',
    'uses' => 'ShiprocketWebhookController@handle',
]);

Route::post('/courier-tracking', [
    'as' => 'webhook.courier_tracking',
    'uses' => 'ShiprocketWebhookController@handle',
]);
