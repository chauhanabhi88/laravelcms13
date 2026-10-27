<?php

return [
    'seller::seller.titles.sellers' => [
        'admin.seller_application.index' => 'seller::seller.permissions.application_list',
        'admin.seller_application.filters' => 'seller::seller.permissions.application_filters',
        'admin.seller_application.show' => 'seller::seller.permissions.application_show',
        'admin.seller_application.approve' => 'seller::seller.permissions.application_approve',
        'admin.seller_application.reject' => 'seller::seller.permissions.application_reject',
        'admin.seller_application.request_changes' => 'seller::seller.permissions.application_request_changes',

        'admin.seller.index' => 'seller::seller.permissions.list',
        'admin.seller.filters' => 'seller::seller.permissions.filters',
        'admin.seller.show' => 'seller::seller.permissions.show',
        'admin.seller.edit' => 'seller::seller.permissions.edit',
        'admin.seller.suspend' => 'seller::seller.permissions.suspend',
        'admin.seller.reactivate' => 'seller::seller.permissions.reactivate',
        'admin.seller.kyc_view' => 'seller::seller.permissions.kyc_view',
        'admin.seller.bank_verify_manual' => 'seller::seller.permissions.bank_verify_manual',
    ],
];
