<?php

/*
| Promotion settings (admin > Settings > Promotions). Read through
| Modules\Promotion\Support\PromotionSettings, never with settings() directly; blank values fall
| back to config('promotion.defaults').
*/

return [
    'promotion::promotion.settings.group' => [
        'allow_stacking' => [
            'label' => 'promotion::promotion.settings.allow_stacking.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.allow_stacking.comment',
        ],
        'max_coupon_discount_percent_of_cart' => [
            'label' => 'promotion::promotion.settings.max_percent.label',
            'type' => 'text',
            'placeholder' => '90',
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.max_percent.comment',
        ],
        'first_order_prepaid_only' => [
            'label' => 'promotion::promotion.settings.first_order_prepaid_only.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.first_order_prepaid_only.comment',
        ],
        'referral_enabled' => [
            'label' => 'promotion::promotion.settings.referral_enabled.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.referral_enabled.comment',
        ],
        'referral_reward_amount' => [
            'label' => 'promotion::promotion.settings.referral_reward_amount.label',
            'type' => 'text',
            'placeholder' => '100',
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.referral_reward_amount.comment',
        ],
        'referral_reward_min_cart' => [
            'label' => 'promotion::promotion.settings.referral_reward_min_cart.label',
            'type' => 'text',
            'placeholder' => '299',
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.referral_reward_min_cart.comment',
        ],
        'referral_reward_validity_days' => [
            'label' => 'promotion::promotion.settings.referral_reward_validity_days.label',
            'type' => 'text',
            'placeholder' => '30',
            'storage' => 'db',
            'comment' => 'promotion::promotion.settings.referral_reward_validity_days.comment',
        ],
    ],
];
