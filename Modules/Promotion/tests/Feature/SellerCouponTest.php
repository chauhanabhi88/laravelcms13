<?php

use Modules\Promotion\Models\Coupon;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Seller/tests/Helpers.php';

beforeEach(function () {
    promotionSetup();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerCouponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'shop15',
        'name' => 'Shop week',
        'type' => 'percent',
        'value' => '15',
        'max_discount' => '200',
        'min_cart_value' => '299',
        'scope' => 'seller',
        'usage_limit_per_customer' => 1,
        'starts_at' => now()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        'is_visible' => 1,
    ], $overrides);
}

test('a seller creates a coupon funded by them and limited to their products', function () {
    [$seller, $owner] = sellerWithOwner();

    $this->actingAs($owner, 'seller')->get(frontRoute('seller.coupon.index'))->assertOk();
    $this->actingAs($owner, 'seller')->get(frontRoute('seller.coupon.create'))->assertOk();
    $this->actingAs($owner, 'seller')->post(frontRoute('seller.coupon.store'), sellerCouponPayload())->assertRedirect(frontRoute('seller.coupon.index'));

    $coupon = Coupon::query()->where('code', 'SHOP15')->firstOrFail();

    expect($coupon)
        ->seller_id->toBe($seller->id)
        ->funded_by->toBe(Coupon::FUNDED_BY_SELLER)
        ->scope->toBe(Coupon::SCOPE_SELLER)
        ->scope_ids->toBe([$seller->id])
        ->is_auto->toBe((int) config('core.no'))
        ->and($coupon->isEnabled())->toBeTrue();

    $this->actingAs($owner, 'seller')->get(frontRoute('seller.coupon.index'))->assertSee('data-seller-coupon="SHOP15"', false);
});

test('product-scoped seller coupons accept only the seller\'s own products', function () {
    [$seller, $owner] = sellerWithOwner();
    $mine = cartVariant([], $seller);
    $foreign = cartVariant();

    $this->actingAs($owner, 'seller')->post(frontRoute('seller.coupon.store'), sellerCouponPayload(['scope' => 'product', 'product_ids' => [$foreign->product_id]]))->assertRedirect();
    expect(Coupon::query()->where('code', 'SHOP15')->exists())->toBeFalse();

    $this->actingAs($owner, 'seller')->post(frontRoute('seller.coupon.store'), sellerCouponPayload(['scope' => 'product', 'product_ids' => [$mine->product_id]]))->assertRedirect(frontRoute('seller.coupon.index'));
    expect(Coupon::query()->where('code', 'SHOP15')->firstOrFail()->scopeIdList())->toBe([$mine->product_id]);
});

test('another seller\'s coupon answers 404 and the owner can edit and switch it off', function () {
    [$seller, $owner] = sellerWithOwner();
    [, $otherOwner] = sellerWithOwner();
    $coupon = Coupon::factory()->forSeller($seller)->create(['code' => 'MYSHOP']);

    $this->actingAs($otherOwner, 'seller')->get(frontRoute('seller.coupon.edit', [$coupon->id]))->assertNotFound();
    $this->actingAs($otherOwner, 'seller')->put(frontRoute('seller.coupon.update', [$coupon->id]), sellerCouponPayload(['code' => 'MYSHOP']))->assertNotFound();
    $this->actingAs($otherOwner, 'seller')->post(frontRoute('seller.coupon.toggle', [$coupon->id]))->assertNotFound();

    $this->actingAs($owner, 'seller')->get(frontRoute('seller.coupon.edit', [$coupon->id]))->assertOk()->assertSee('MYSHOP');
    $this->actingAs($owner, 'seller')->put(frontRoute('seller.coupon.update', [$coupon->id]), sellerCouponPayload(['code' => 'MYSHOP', 'name' => 'Renamed']))->assertRedirect();
    $this->actingAs($owner, 'seller')->post(frontRoute('seller.coupon.toggle', [$coupon->id]))->assertRedirect();

    expect($coupon->fresh())->name->toBe('Renamed')->funded_by->toBe(Coupon::FUNDED_BY_SELLER)
        ->and($coupon->fresh()->isEnabled())->toBeFalse();
});
