<?php

return [
    'directory::directory.permissions.directory' => [
        'admin.directory.index' => 'directory::directory.permissions.list',
        'admin.directory.create' => 'directory::directory.permissions.create',
        'admin.directory.edit' => 'directory::directory.permissions.edit',

        'admin.country.index' => 'directory::country.permissions.list',
        'admin.country.save' => 'directory::country.permissions.save',
        'admin.country.export' => 'directory::country.permissions.country_export',
        'admin.country.import' => 'directory::country.permissions.country_import',
        'admin.country.get_cities' => 'directory::country.permissions.cities',

        'admin.city.import' => 'directory::country.permissions.city_import',
        'admin.city.export' => 'directory::country.permissions.city_export',
        'admin.state.import' => 'directory::country.permissions.state_import',
        'admin.state.export' => 'directory::country.permissions.state_export',
    ],
    'directory::directory_pincode.titles.directory_pincode' => [
        'admin.directory_pincode.index' => 'directory::directory_pincode.labels.list',
        'admin.directory_pincode.filters' => 'directory::directory_pincode.labels.filters',
        'admin.directory_pincode.edit' => 'directory::directory_pincode.labels.edit',
    ],
];
