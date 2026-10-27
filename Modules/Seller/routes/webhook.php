<?php

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| /webhooks/{provider}: no session, no CSRF; each controller verifies the provider's
| signature before reading the payload.
|
*/

Route::post('/razorpayx', [
    'as' => 'webhook.razorpayx',
    'uses' => 'RazorpayXController@handle',
]);
