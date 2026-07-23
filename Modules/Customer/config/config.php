<?php

return [
    'name' => 'Customer',
    /*
    |--------------------------------------------------------------------------
    | Define which route to redirect to after a successful login
    |--------------------------------------------------------------------------
    */
    'redirect_route_after_login' => 'customer.myaccount',
    /*
    |--------------------------------------------------------------------------
    | Define which route the user should be redirected to after accessing
    | a resource that requires to be logged in
    |--------------------------------------------------------------------------
    */
    'redirect_route_not_logged_in' => 'homepage',
    /*
    |--------------------------------------------------------------------------
    | Login column
    |--------------------------------------------------------------------------
    | Define which column you'd like to use to login with, currently
    */
    'login_column' => 'email',
    'disabled_status' => 2,
    'enabled_status' => 1,
    /*
    |--------------------------------------------------------------------------
    | Profile picture defaults
    |--------------------------------------------------------------------------
    | Used when the matching entries under Settings > Customer are left blank.
    | default_image_size is in kilobytes.
    */
    'default_image_type' => 'jpeg,jpg,png',
    'default_image_size' => 2048,
    'email_verification' => [
        'yes' => 1,
        'no' => 2,
    ],
    'is_new' => [
        'yes' => 1,
        'no' => 2,
    ],
    'signup_type' => [
        'email_verification' => ['label' => 'Email Verification', 'code' => 'email_verification'],
        'auto_active' => ['label' => 'Auto Active', 'code' => 'auto_active'],
        'admin' => ['label' => 'Admin', 'code' => 'admin'],
    ],

    'is_default_address' => [
        'yes' => 1,
        'no' => 2,
    ],
    'enable_customer_group' => 1,

    /* log config */

    'customer_general_front' => [
        'file_name' => 'customer_general_front',
        'is_enabled' => 1,
        'module' => 'customer',
    ],
    'cache' => [
        'deleted_customer_name' => 'DeletedCustomer',
        'name' => 'Customer',
        'customer_login_log' => 'CustomerLoginLog',
        'customer_group_name' => 'CustomerGroup',
        'customer_online_offline_log' => 'CustomerOnLineOffLineLog',
    ],
    'customer_log' => [
        'online' => '1',
        'offline' => '2',
    ],
    'show_customer_online_offline_grid' => 1,
    'customerLoginLogArr' => [
        'login' => [
            'action' => 'login',
        ],
        'logout' => [
            'action' => 'logout',
        ],
    ],
    'lang_path' => 'customer::customer.labels',
    'registered_with' => [
        'email' => 1,
        'facebook' => 2,
        'apple' => 3,
    ],
    'error_code' => [
        'email_unique' => '0162',
        'invalid_auth_code' => '001',
        'please_login' => '0142',
        'invalid_username' => '0152',
        'data_invalid' => '0133',
        'mobile_valid' => '0134',
        'password_required' => '0138',
        'country_code_valid' => '0139',
        'duplicate_customer' => '0140',
        'duplicate_customer_email' => '0141',
        'already_exists' => '0132',
        'invalid_otp' => '0143',
        'invalid_otp_id' => '0144',
        'invalid_otp_number' => '0145',
        'otp_already_use' => '0146',
        'customer_not_found' => '0147',
        'mobile_not_link_with_otp' => '0148',
        'logout_fail' => '0149',
        'new_password_required' => '0150',
        'new_password_mismatch' => '0151',
        'old_password_mismatch' => '0153',
        'old_password_required' => '0154',
        'new_paaword_diff_then_old' => '0155',
        'no_rider_available' => '0159',
        'mobile_not_registered' => '0160',
        'access_token_not_found' => '0161',
        'otp_info_not_exist' => '0162',
        'oauth_token' => '002',
        'facebook_account_converted' => '0232',
        'deleted_customer' => '0233',
        'account_not_register' => '0234',
        'apple_account_converted' => '0235',
        'otp_limit_reached' => '0236',
    ],
    'device_type' => [
        'android' => 'android',
        'ios' => 'ios',
    ],
];
