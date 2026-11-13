<?php

return [
    'name' => 'User',
    /*
    |--------------------------------------------------------------------------
    | Define which route to redirect to after a successful login
    |--------------------------------------------------------------------------
    */
    'redirect_route_after_login' => 'admin.dashboard.index',
    /*
    |--------------------------------------------------------------------------
    | Define which route the user should be redirected to after accessing
    | a resource that requires to be logged in
    |--------------------------------------------------------------------------
    */
    'redirect_route_not_logged_in' => 'backend_login',
    /*
    |--------------------------------------------------------------------------
    | Login column
    |--------------------------------------------------------------------------
    | Define which column you'd like to use to login with, currently
    */
    'login_column' => 'email',
    /*
    |--------------------------------------------------------------------------
    | Login throttling
    |--------------------------------------------------------------------------
    | Failed login attempts are counted per client IP. Once max_attempts is
    | reached the IP is blocked for the remainder of the decay window. The same
    | limits are reused for the forgot-password endpoint.
    */
    'login_throttle' => [
        'max_attempts' => 10,
        'decay_seconds' => 300,
    ],
    /*
    | Admin password policy (plan M21 S10): minimum length; the create, edit and reset forms
    | also require a lowercase and uppercase letter, a digit and a special character.
    */
    'password_min_length' => (int) env('ADMIN_PASSWORD_MIN_LENGTH', 10),
    'master_admin_slug' => 'master_admin',
    'cache' => [
        'deleted_user_name' => 'DeletedUser',
        'name' => 'User',
    ],
    'lang_path' => 'user::user.labels',
];
