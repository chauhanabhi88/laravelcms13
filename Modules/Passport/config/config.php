<?php

/*
| Merged into Laravel Passport's own "passport" config namespace by
| PassportServiceProvider. Keep keys in the Passport domain and check
| vendor/laravel/passport/config/passport.php before adding one.
|
| Token scopes are NOT declared here: Modules\Core\Providers\AuthServiceProvider
| is the single source of truth for Passport::tokensCan().
*/

return [
    /*
    | Access / refresh token lifetimes, as ISO-8601 durations.
    */
    'tokens_expire_in' => env('PASSPORT_TOKENS_EXPIRE_IN', 'P7D'),

    'refresh_tokens_expire_in' => env('PASSPORT_REFRESH_TOKENS_EXPIRE_IN', 'P30D'),
];
