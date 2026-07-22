<?php

return [
    'name' => 'Passport',
    'scopes' => [
        'customer' => 'Customer',
        'guest' => 'Guest Users',
        'users' => 'Admin Users',
    ],
    'http_success' => [
        'http_ok' => 200
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
        'failed_dependancy' => 424
    ],
];
