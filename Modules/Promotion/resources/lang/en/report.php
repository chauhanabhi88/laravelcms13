<?php

return [
    'title' => 'Promotion report',
    'permissions' => [
        'index' => 'View the promotion report',
    ],
    'filters' => [
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Show',
    ],
    'summary' => [
        'with' => 'Orders with a promotion',
        'without' => 'Orders without',
        'orders' => ':count orders',
        'aov' => 'AOV :amount',
        'discount' => 'Discount cost',
        'uplift' => 'AOV uplift',
        'uplift_none' => 'Not enough orders to compare',
    ],
    'coupons' => [
        'title' => 'Coupons & offers',
        'code' => 'Code',
        'name' => 'Name',
        'kind' => 'Kind',
        'funded_by' => 'Funded by',
        'orders' => 'Orders',
        'customers' => 'Customers',
        'discount' => 'Discount cost',
        'revenue' => 'Revenue',
        'aov' => 'AOV',
        'auto' => 'Automatic',
        'code_kind' => 'Code',
        'none' => 'No promotion was used in this period.',
    ],
    'deals' => [
        'title' => 'Flash deals',
        'name' => 'Deal',
        'window' => 'Window',
        'units' => 'Units sold',
        'revenue' => 'Revenue',
        'none' => 'No flash-deal sales in this period.',
    ],
];
