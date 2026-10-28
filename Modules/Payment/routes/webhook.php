<?php

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| /webhooks/{provider}: no session, no CSRF, no throttle; the controller verifies the
| provider's signature before reading the payload.
|
*/

Route::post('/razorpay', [
    'as' => 'webhook.razorpay',
    'uses' => 'RazorpayWebhookController@handle',
]);
