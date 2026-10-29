<?php

use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Promotion\Models\Coupon;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    promotionSettings(['max_coupon_discount_percent_of_cart' => 90, 'allow_stacking' => 2]);
});

test('a platform-funded coupon leaves the seller\'s sale credit at the full price', function () {
    $seller = financeSeller();
    $variant = cartVariant(['price' => '1000', 'mrp' => '1000', 'stock' => 10], $seller, ['gst_percent' => '5', 'hsn_code' => '6204']);
    promotionCoupon(['code' => 'PLATFORM100', 'value' => '100']);

    [, $sellerOrder] = deliveredWithCoupon(orderCustomer(), [$variant], 'PLATFORM100');
    $sale = ledgerByType($sellerOrder)[SellerLedgerEntry::TYPE_SALE];

    expect($sale->amount->toDecimal())->toBe('1000.00')
        ->and($sale->meta)->not->toHaveKey('seller_discount');
});

test('a seller-funded coupon lowers the seller\'s sale credit by its share', function () {
    $seller = financeSeller();
    $variant = cartVariant(['price' => '1000', 'mrp' => '1000', 'stock' => 10], $seller, ['gst_percent' => '5', 'hsn_code' => '6204']);
    Coupon::factory()->forSeller($seller)->flat('100')->create(['code' => 'SELLER100']);

    [, $sellerOrder] = deliveredWithCoupon(orderCustomer(), [$variant], 'SELLER100');
    $entries = ledgerByType($sellerOrder);

    expect($entries[SellerLedgerEntry::TYPE_SALE]->amount->toDecimal())->toBe('900.00')
        ->and($entries[SellerLedgerEntry::TYPE_SALE]->meta['seller_discount'])->toBe('100.00')
        // TDS follows the sale actually credited.
        ->and($entries[SellerLedgerEntry::TYPE_TDS]->base_amount->toDecimal())->toBe('900.00');
});
