<?php

return [
    'name' => 'Directory',
    'cache' => [
        'country' => 'Country',
        'city' => 'City',
        'state' => 'State',
        'name' => 'Directory',
        'currency_rate' => 'CurrencyRate',
        'currency_setup' => 'CurrencySetup',
        'pincode' => 'DirectoryPincode',
    ],

    /*
    | Fallbacks for the Directory settings (read through Modules\Directory\Support\DirectorySettings).
    */
    'defaults' => [
        'country' => 'IN',
    ],
];
