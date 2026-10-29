<?php

/*
|--------------------------------------------------------------------------
| API error code catalogue (M20 §3.1)
|--------------------------------------------------------------------------
|
| The envelope's "code" is the HTTP status for ordinary answers (200, 404, 422...) and one of
| the numbers below for errors a mobile app has to react to in a specific way. "http" is the
| status those codes travel with; "message" is a translation key in Core's api.php lang file.
| Keep modules/Core/resources/api/openapi.yaml (ErrorCode enum) in step with this list.
*/

return [
    'codes' => [
        4011 => ['name' => 'token_invalid', 'http' => 401, 'message' => 'core::api.codes.token_invalid'],
        4012 => ['name' => 'token_revoked', 'http' => 401, 'message' => 'core::api.codes.token_revoked'],
        4031 => ['name' => 'seller_suspended', 'http' => 403, 'message' => 'core::api.codes.seller_suspended'],
        4091 => ['name' => 'price_changed', 'http' => 409, 'message' => 'core::api.codes.price_changed'],
        4092 => ['name' => 'out_of_stock', 'http' => 409, 'message' => 'core::api.codes.out_of_stock'],
        4221 => ['name' => 'cod_not_eligible', 'http' => 422, 'message' => 'core::api.codes.cod_not_eligible'],
        4291 => ['name' => 'otp_rate_limited', 'http' => 429, 'message' => 'core::api.codes.otp_rate_limited'],
    ],

    /*
    | Stable "reason" strings raised by domain exceptions (CheckoutException::$reasonCode ...)
    | mapped to the code above. A reason starting with one of the prefixes maps too.
    */
    'reasons' => [
        'price_changed' => 4091,
        'out_of_stock' => 4092,
        'seller_suspended' => 4031,
    ],

    'reason_prefixes' => [
        'cod_' => 4221,
    ],
];
