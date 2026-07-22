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
    'master_admin_slug' => 'master_admin',
    'cache' => [
        "deleted_user_name" => "DeletedUser",
		'name' => 'User',
	],
    'lang_path' => 'user::user.labels'
];
