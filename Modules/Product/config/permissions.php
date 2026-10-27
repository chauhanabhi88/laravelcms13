<?php

return [
    'product::product.titles.products' => [
        'admin.product.index' => 'product::product.permissions.list',
        'admin.product.filters' => 'product::product.permissions.filters',
        'admin.product.show' => 'product::product.permissions.show',
        'admin.product.block' => 'product::product.permissions.block',
        'admin.product.unblock' => 'product::product.permissions.unblock',
        'admin.product.export' => 'product::product.permissions.export',

        'admin.product_qc.index' => 'product::product.permissions.qc_list',
        'admin.product_qc.filters' => 'product::product.permissions.qc_filters',
        'admin.product_qc.review' => 'product::product.permissions.qc_review',
        'admin.product_qc.approve' => 'product::product.permissions.qc_approve',
        'admin.product_qc.reject' => 'product::product.permissions.qc_reject',
        'admin.product_qc.bulk' => 'product::product.permissions.qc_bulk',

        'admin.inventory_movement.index' => 'product::product.permissions.movement_list',
        'admin.inventory_movement.filters' => 'product::product.permissions.movement_filters',
    ],
];
