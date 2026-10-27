<?php

use Modules\Seller\Support\SellerSettings;

require_once __DIR__.'/../Helpers.php';

test('the Seller settings group renders in admin settings', function () {
    $admin = sellerAdmin(['admin.settings.index', 'admin.settings.getModuleSetting']);

    $response = $this->actingAs($admin)->getJson(route('admin.settings.getModuleSetting', ['module' => 'Seller']));

    $response->assertSuccessful();
    expect(json_encode($response->json()))->toContain('bank_verifier')->toContain('name_match_threshold');
});

test('settings fall back to the config defaults', function () {
    expect(SellerSettings::bankVerifier())->toBe('manual')
        ->and(SellerSettings::gstVerifier())->toBe('null')
        ->and(SellerSettings::allowsNonGstSellers())->toBeFalse()
        ->and(SellerSettings::nameMatchThreshold())->toBe(80);
});
