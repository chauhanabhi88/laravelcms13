<?php

return [
    'name' => 'Seller',

    /*
    | URL prefix of the supplier panel (/supplier/...). Keep in step with core.seller-prefix,
    | which the guest redirect uses before this module boots.
    */
    'panel_prefix' => 'supplier',

    /*
    | Version of the supplier agreement a seller accepts in step 5. Bump it when the
    | agreement text (lang seller::agreement) changes; the accepted version is stored.
    */
    'agreement_version' => '2026-11',

    /*
    | KYC uploads: private disk only, served through an authorised controller.
    */
    'kyc_disk' => 'private',
    'kyc_folder' => 'seller_kyc',
    'kyc_max_file_mb' => 5,
    'kyc_mimes' => 'pdf,jpg,jpeg,png',

    /*
    | Store logos (public disk, /storage/seller_logo/...).
    */
    'logo_folder' => 'seller_logo',
    'logo_mimes' => 'jpg,jpeg,png,webp',
    'logo_max_kb' => 1024,

    /*
    | Entity names: cache buckets (BaseCacheDecorator) and session filter keys of the admin grids.
    */
    'cache' => [
        'seller' => 'Seller',
        'seller_application' => 'SellerApplication',
        'seller_user' => 'SellerUser',
        'bank_account' => 'SellerBankAccount',
        'warehouse' => 'SellerWarehouse',
        'kyc_document' => 'SellerKycDocument',
    ],

    'business_types' => ['proprietorship', 'partnership', 'llp', 'pvt_ltd', 'individual'],

    /*
    | Supplier staff roles. "owner" can do everything; the others open their panel area
    | (middleware seller.can:<role>).
    */
    'roles' => ['owner', 'catalog', 'orders', 'finance'],

    'kyc_document_types' => ['gst_certificate', 'pan_card', 'cancelled_cheque', 'signature', 'address_proof', 'other'],

    /*
    | Document types a seller holds one of: a new upload replaces the old file.
    */
    'kyc_single_types' => ['gst_certificate', 'pan_card', 'cancelled_cheque', 'signature', 'address_proof'],

    'bank_account_types' => ['savings', 'current'],

    /*
    | Fallbacks for admin > Settings > Seller, read through Modules\Seller\Support\SellerSettings.
    */
    'defaults' => [
        'bank_verifier' => 'manual',
        'gst_verifier' => 'null',
        'gst_verifier_url' => '',
        'gst_verifier_key' => '',
        'allow_non_gst_sellers' => 2,
        'name_match_threshold' => 80,
    ],

    'bank_verifiers' => ['razorpayx', 'manual', 'fake'],
    'gst_verifiers' => ['null', 'http'],

    /*
    | RazorpayX fund account validation (penny drop). Keys stay in .env.
    */
    'razorpayx' => [
        'base_url' => env('RAZORPAYX_BASE_URL', 'https://api.razorpay.com'),
        'key_id' => env('RAZORPAYX_KEY_ID'),
        'key_secret' => env('RAZORPAYX_KEY_SECRET'),
        'account_number' => env('RAZORPAYX_ACCOUNT_NUMBER'),
        'webhook_secret' => env('RAZORPAYX_WEBHOOK_SECRET'),
        // Paise deposited by the validation.
        'validation_amount' => 100,
    ],

    /*
    | Public IFSC directory (bank + branch for an IFSC); answers are cached.
    */
    'ifsc_lookup' => [
        'url' => 'https://ifsc.razorpay.com/',
        'cache_days' => 30,
        'miss_cache_minutes' => 60,
    ],

    /*
    | Onboarding wizard: how often the bank step polls for a penny-drop result, and for how long.
    */
    'bank_poll' => [
        'interval_seconds' => 5,
        'timeout_seconds' => 60,
    ],

    /*
    | Status filter of the admin KYC queue.
    */
    'application_statuses' => ['pending_verification', 'changes_requested'],

    /*
    | Extra dashboard cards: view names other modules append (e.g. Product's low-stock card).
    */
    'dashboard_widgets' => [],

    /*
    | Reason templates offered on the admin decision forms (lang keys under seller::seller.reason_templates).
    */
    'reason_templates' => [
        'reject' => ['documents_invalid', 'gst_mismatch', 'prohibited_business', 'duplicate_account'],
        'request_changes' => ['gst_certificate_unclear', 'bank_name_mismatch', 'pickup_address_incomplete', 'pan_unclear'],
        'suspend' => ['policy_violation', 'quality_issues', 'fraud_review'],
    ],
];
