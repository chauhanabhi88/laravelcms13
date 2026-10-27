<?php

return [
    'titles' => [
        'directory_pincode' => 'Pincodes',
        'list' => 'Pincodes',
        'edit_directory_pincode' => 'Edit Pincode',
        'directory_pincode_info' => 'Pincode Information',
    ],
    'labels' => [
        'list' => 'Pincode List',
        'filters' => 'Filter Pincodes',
        'edit' => 'Edit Pincode / Toggle Flags',
        'pincode' => 'Pincode',
        'office_name' => 'Post Office',
        'district' => 'District',
        'state' => 'State',
        'city' => 'City',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'is_serviceable' => 'Serviceable',
        'cod_allowed' => 'Cash on Delivery',
        'zone' => 'Zone',
    ],
    'columns' => [
        'id' => 'ID',
        'pincode' => 'Pincode',
        'office_name' => 'Post Office',
        'district' => 'District',
        'state_id' => 'State',
        'city_id' => 'City',
        'is_serviceable' => 'Serviceable',
        'cod_allowed' => 'COD',
        'zone' => 'Zone',
        'updated_at' => 'Updated At',
    ],
    'zones' => [
        'metro' => 'Metro',
        'roi' => 'Rest of India',
        'ne_jk' => 'North East / J&K',
        'special' => 'Special',
    ],
    'messages' => [
        'updated_success' => 'Pincode updated successfully.',
        'data_invalid' => 'The pincode record was not found.',
        'not_found' => 'We could not find this pincode.',
        'flags_apply_to_pincode' => 'Serviceable, Cash on Delivery and Zone apply to every post office with this pincode.',
        'import_hint' => 'Pincodes are loaded with "php artisan directory:import-pincodes <csv>". Toggles apply to every post office sharing the pincode.',
    ],
    'settings' => [
        'default_country' => [
            'label' => 'Default Country Code',
            'comment' => 'ISO-2 code (e.g. IN) of the country pincodes, states and cities belong to.',
        ],
    ],
];
