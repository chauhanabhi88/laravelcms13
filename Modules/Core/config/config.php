<?php

return [
    'name' => 'Core',
    /*
    |--------------------------------------------------------------------------
    | The prefix that'll be used for the administration
    |--------------------------------------------------------------------------
    */
    'admin-prefix' => 'backend',

    /*
    |--------------------------------------------------------------------------
    | Location where your themes are located
    |--------------------------------------------------------------------------
    */
    'themes_path' => base_path().'/themes',

    /*
    |--------------------------------------------------------------------------
    | Which administration theme to use for the back end interface
    |--------------------------------------------------------------------------
    */
    'admin-theme' => 'admin',

    'skin' => 'skin-black',
    'default_per_page' => 20,
    'cache_expired_after' => 3600, // cache expired after seconds

    /*
    | common config options
    */
    'yes' => 1,
    'no' => 2,
    'enabled' => 1,
    'disabled' => 2,
    'defaultPerPage' => 20,
    'on' => 1,
    'off' => 2,

    /*
    | common input type maxlength
    */
    'smallint_maxlength' => '6',
    'varchar_maxlength' => '255',

    /**
     * Entity Join Options
     */
    'hasOne' => 1,
    'hasMany' => 2,
    'belongsToOne' => 3,
    'belongsToMany' => 4,

    'oneToOne' => 1,
    'oneToMany' => 2,
    'manyToOne' => 3,
    'ManyToMany' => 4,

    'create' => 1,
    'update' => 2,
    'delete' => 3,

    'isBaseCurrency' => 1,
    'isDisplayCurrency' => 1,
    /*
    | Used by encrypt_It() / decrypt_It() to obfuscate record ids in urls.
    | The previous default (bf-cbc) is no longer shipped by OpenSSL 3, where it
    | made every encrypted id come back as an empty string.
    */
    'encrypt' => [
        'password' => env('CORE_ENCRYPT_PASSWORD', 'sc3R7469'),
        'method' => env('CORE_ENCRYPT_METHOD', 'aes-128-cbc'),
        'iv' => env('CORE_ENCRYPT_IV', '9572483695724836'),
        'datepicker_format' => 'dd-mm-yy',
        'php_datepicker_format' => 'd-m-Y',
    ],

    /*
    | flag for backend url language perams
    */
    'translation' => 0,

    /*
    | flag for frontend url language perams
    */
    'translation_front' => 0,

    /*
    | flag for api language perams
    */
    'translation_api' => 0,

    /*
    | flag for define route type
    */
    'route_type' => 'front',

    /*
        for enabled or disabled file log
    */
    'is_filelog_enabled' => 1,

    'summernote_temp_folder_name' => 'summernote_temp',

    'special_character_regex_server' => '/[@$\"!%*#?&.{|}()+,:;\\[\\]<\\\=>^_`~\'-\\/]/',

    'cache' => [
        'notification_log' => 'NotificationLog',
    ],

    'api_versions' => [
        'v1',
    ],

    'cache_file_log' => [
        'file_name' => 'cache_file_log',
        'is_enabled' => 1,
        'module' => 'core',
    ],

    /*
    | HTTP status codes used by ApiController response helpers.
    */
    'http_success' => [
        'http_ok' => 200,
    ],

    'http_fail' => [
        'bad_request' => 400,
        'internal_server_error' => 500,
        'unauthorized' => 401,
        'forbidden' => 403,
        'not_found' => 404,
        'method_not_allowed' => 405,
        'not_acceptable' => 406,
        'exceptional_fail' => 417,
        'failed_dependancy' => 424,
    ],

    /*
    | URL prefix of the supplier (seller) panel. Guests hitting an "auth:seller" route are
    | sent to the "seller.login" route, or to /{seller-prefix}/login until that route exists.
    */
    'seller-prefix' => 'supplier',

    /*
    | HasStateMachine: the actor_type written to status_history for each actor model.
    | Unlisted models fall back to their snake_cased class name; no actor means "system".
    */
    'state_machine' => [
        'actor_types' => [
            'Modules\User\Models\User' => 'admin',
            'Modules\Customer\Models\Customer' => 'customer',
            'Modules\Seller\Models\SellerUser' => 'seller',
        ],
    ],

    /*
    | Outbound HTTP to third-party integrations: Http::integration('<name>').
    | Requests and responses are logged to the "integrations" log channel with secrets redacted.
    */
    'integrations' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retries' => 3,
        'retry_delay_ms' => 200,
        'log_bodies' => true,
        'log_body_limit' => 2000,
        'redact_keys' => ['authorization', 'authkey', 'api_key', 'apikey', 'key', 'secret', 'token', 'password', 'otp', 'signature', 'pan', 'account_number'],
    ],

    /*
    | SMS gateway behind Modules\Core\Contracts\SmsDriver: log (writes storage/logs/sms.log),
    | fake (keeps messages in memory, for tests) or msg91. DLT template ids live per template key.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'msg91' => [
            'endpoint' => env('MSG91_FLOW_ENDPOINT', 'https://control.msg91.com/api/v5/flow'),
            'auth_key' => env('MSG91_AUTH_KEY'),
            'sender_id' => env('MSG91_SENDER_ID'),
            'templates' => [
                'otp' => env('MSG91_OTP_TEMPLATE_ID'),
            ],
        ],
    ],

    /*
    | One-time passwords (Modules\Core\Services\Otp\OtpService).
    */
    'otp' => [
        'length' => 6,
        'expires_minutes' => 5,
        'resend_cooldown_seconds' => 30,
        'max_attempts' => 5,
        'max_sends_per_identifier_per_hour' => 5,
        'max_sends_per_ip_per_hour' => 20,
        'prune_after_hours' => 24,
    ],

];
