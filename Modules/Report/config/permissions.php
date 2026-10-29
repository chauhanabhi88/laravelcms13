<?php

return [
    'report::report.permission_groups.reports' => [
        'admin.report.dashboard' => 'report::report.permissions.dashboard',
        'admin.report.sales' => 'report::report.permissions.sales',
        'admin.report.fulfilment' => 'report::report.permissions.fulfilment',
        'admin.report.returns' => 'report::report.permissions.returns',
        'admin.report.sellers' => 'report::report.permissions.sellers',
        'admin.report.products' => 'report::report.permissions.products',
        'admin.report.geography' => 'report::report.permissions.geography',
        'admin.report.payments' => 'report::report.permissions.payments',
        'admin.report.search' => 'report::report.permissions.search',
    ],
    'report::report.permission_groups.exports' => [
        'admin.report.export' => 'report::report.permissions.export',
    ],
];
