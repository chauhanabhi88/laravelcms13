<?php
return [
    'titles' => [
        'country'               => 'Country',
        'country_list'          => 'Country List',
        'city'                  => 'City',
        'user'                  => 'Country Information',
        'state'                  => 'State'
    ],
    'labels' => [
        'list'                  => 'Country List',
        'save'                  => 'Country Save',
        'country'               => 'Country',
        'cities'                => 'Cities list',
        'city_import'           => 'City Import',
        'city_export'           => 'City Export',
    ],
    'buttons' =>[
        'export'                => 'Export',
        'import'                => 'Import',
        'import_sample'         => 'Sample of Import File',
        'import_country'        => 'Import Countries',
        'import_state'          => 'Import States',
        'import_city'           => 'Import Cities'
    ],
    'messages' => [
        'data_invalid'          => 'Resource data invalid.',
        'updated_success'       => 'Country updated successfully.',
        'invalid_header'        => 'Incorrect CSV header',
        'invalid_content'       => 'Incorrect / Incomplete data',
        'import_success_msg'    => 'File imported successfully.',
        'invalid_encoding'      =>  'Incorrect / Incomplete data at row(s): ',
        "empty_state"           => " No records for state found.Please add state first.",
        "empty_country"         => " No records for country found.Please add country first."
    ],
    'permissions' => [
        'list'                  => 'Country List',
        'save'                  => 'Country Save',
        'cities'                => 'Cities list',
        'city_import'           => 'City Import',
        'city_export'           => 'City Export',
        'state_import'          => 'State Import',
        'state_export'          => 'State Export',
        'country_export'        => 'Country Export',
        'country_import'        => 'Country Import'
    ],
    'settings' => [
        'country' => 'Country,State,City',
        "max_upload" => [
            "label" => "Max Size",
            "comment" => "Enter Value in Megabytes",
        ],
    ]
];
