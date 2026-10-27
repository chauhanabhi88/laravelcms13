<?php

/*
| Seller onboarding settings (admin > Settings > Seller). Read through
| Modules\Seller\Support\SellerSettings, never with settings() directly.
*/

return [
    'seller::seller.settings.seller' => [
        'bank_verifier' => [
            'label' => 'seller::seller.settings.bank_verifier.label',
            'type' => 'select',
            'options' => [
                'manual' => 'Manual review (cancelled cheque)',
                'razorpayx' => 'RazorpayX penny drop',
                'fake' => 'Fake (local testing only)',
            ],
            'storage' => 'db',
            'comment' => 'seller::seller.settings.bank_verifier.comment',
        ],
        'name_match_threshold' => [
            'label' => 'seller::seller.settings.name_match_threshold.label',
            'type' => 'number',
            'placeholder' => '80',
            'storage' => 'db',
            'comment' => 'seller::seller.settings.name_match_threshold.comment',
            'min' => 50,
            'max' => 100,
        ],
        'gst_verifier' => [
            'label' => 'seller::seller.settings.gst_verifier.label',
            'type' => 'select',
            'options' => [
                'null' => 'Format and checksum only',
                'http' => 'External GSTIN API',
            ],
            'storage' => 'db',
            'comment' => 'seller::seller.settings.gst_verifier.comment',
        ],
        'gst_verifier_url' => [
            'label' => 'seller::seller.settings.gst_verifier_url.label',
            'type' => 'text',
            'placeholder' => 'https://gst-api.example.com/v1/gstin',
            'storage' => 'db',
            'comment' => 'seller::seller.settings.gst_verifier_url.comment',
        ],
        'gst_verifier_key' => [
            'label' => 'seller::seller.settings.gst_verifier_key.label',
            'type' => 'password',
            'placeholder' => 'seller::seller.settings.gst_verifier_key.label',
            'storage' => 'db',
        ],
        'allow_non_gst_sellers' => [
            'label' => 'seller::seller.settings.allow_non_gst_sellers.label',
            'type' => 'select',
            'options' => [
                2 => 'No',
                1 => 'Yes',
            ],
            'storage' => 'db',
            'comment' => 'seller::seller.settings.allow_non_gst_sellers.comment',
        ],
    ],
];
