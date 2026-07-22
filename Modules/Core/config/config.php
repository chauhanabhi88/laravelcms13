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
    'themes_path' => base_path() . '/themes',

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
    'yes'               => 1,
    'no'                => 2,
    'enabled'           => 1,
    'disabled'          => 2,
    'defaultPerPage'    => 20,
    'on'                => 1,
    'off'               => 2,


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
    'encrypt' => [
        'password'  => 'sc3R7469',
        'method'    => 'bf-cbc',
        'iv'        => '95724836',
        'datepicker_format' => 'dd-mm-yy',
        'php_datepicker_format' => 'd-m-Y',
    ],

    /*
    | flag for backend url language perams
    */
    "translation" => 0,

    /*
    | flag for frontend url language perams
    */
    "translation_front" => 0,

    /*
    | flag for api language perams
    */
    "translation_api" => 0,

    /*
    | flag for define route type
    */
    'route_type' => 'front',

    /*
        for enabled or disabled file log
    */
    'is_filelog_enabled' => 1,

    'summernote_temp_folder_name'  => 'summernote_temp',

    'special_character_regex_server' => '/[@$\"!%*#?&.{|}()+,:;\\[\\]<\\\=>^_`~\'-\\/]/',

    'cache' => [
        "notification_log" => "NotificationLog"
    ],

    "api_versions" => [
        "v1"
    ],

    'cache_file_log' => [
        'file_name' => 'cache_file_log',
        'is_enabled' => 1,
        'module' => 'core'
    ],
    
];
