<?php

return [
    'promotion::promotion.permission_groups.coupons' => [
        'admin.coupon.index' => 'promotion::coupon.permissions.index',
        'admin.coupon.filters' => 'promotion::coupon.permissions.filters',
        'admin.coupon.create' => 'promotion::coupon.permissions.create',
        'admin.coupon.edit' => 'promotion::coupon.permissions.edit',
        'admin.coupon.delete' => 'promotion::coupon.permissions.delete',
        'admin.coupon.bulk_generate' => 'promotion::coupon.permissions.bulk_generate',
    ],
    'promotion::promotion.permission_groups.flash_deals' => [
        'admin.flash_deal.index' => 'promotion::flash_deal.permissions.index',
        'admin.flash_deal.filters' => 'promotion::flash_deal.permissions.filters',
        'admin.flash_deal.create' => 'promotion::flash_deal.permissions.create',
        'admin.flash_deal.edit' => 'promotion::flash_deal.permissions.edit',
        'admin.flash_deal.delete' => 'promotion::flash_deal.permissions.delete',
    ],
    'promotion::promotion.permission_groups.reports' => [
        'admin.promotion_report.index' => 'promotion::report.permissions.index',
    ],
];
