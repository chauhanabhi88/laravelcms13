<?php

use Illuminate\Support\ViewErrorBag;
use Modules\Core\Support\Money;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\FlashDeal;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

/**
 * Validation messages flashed by the last request (this app's session keeps them as a plain array).
 *
 * @return array<string, mixed>
 */
function promotionErrors(): array
{
    $errors = session('errors');

    return $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : (array) ($errors['default']['messages'] ?? []);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'diwali100',
        'name' => 'Festive savings',
        'type' => 'flat',
        'value' => '100',
        'min_cart_value' => '499',
        'scope' => 'all',
        'funded_by' => 'platform',
        'usage_limit_per_customer' => 1,
        'starts_at' => now()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        'is_visible' => 1,
        'status' => 1,
    ], $overrides);
}

test('every promotion screen renders for a promotion admin', function () {
    $admin = promotionAdmin();
    $coupon = promotionCoupon(['code' => 'GRIDME']);
    $deal = FlashDeal::factory()->create();

    foreach (['coupon', 'flash_deal'] as $grid) {
        $this->actingAs($admin)->get(route('admin.'.$grid.'.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.'.$grid.'.filters'))->assertOk()->assertJson(['type' => 'success']);
        $this->actingAs($admin)->get(route('admin.'.$grid.'.create'))->assertOk();
    }

    $this->actingAs($admin)->post(route('admin.coupon.filters'))->assertJson(fn ($json) => $json->where('content.html', fn ($html) => str_contains($html, 'GRIDME'))->etc());
    $this->actingAs($admin)->get(route('admin.coupon.edit', [$coupon->id]))->assertOk()->assertSee('GRIDME');
    $this->actingAs($admin)->get(route('admin.flash_deal.edit', [$deal->id]))->assertOk()->assertSee($deal->name);
    $this->actingAs($admin)->get(route('admin.coupon.bulk'))->assertOk();
    $this->actingAs($admin)->get(route('admin.promotion_report.index'))->assertOk()->assertSee(trans('promotion::report.title'));
});

test('promotion screens need their permission', function () {
    $admin = promotionAdmin(['admin.coupon.index']);

    $this->actingAs($admin)->get(route('admin.coupon.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.flash_deal.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload())->assertForbidden();
    $this->actingAs($admin)->get(route('admin.promotion_report.index'))->assertForbidden();
});

test('coupons are created, validated, edited and deleted only while unused', function () {
    $admin = promotionAdmin();
    $seller = orderSeller();

    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload(['type' => 'percent', 'value' => '150']))->assertRedirect();
    expect(promotionErrors())->toHaveKey('value');

    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload(['funded_by' => 'seller']))->assertRedirect();
    expect(promotionErrors())->toHaveKey('seller_id');

    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload(['scope' => 'product']))->assertRedirect();
    expect(promotionErrors())->toHaveKey('scope_ids');

    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload([
        'scope' => 'seller', 'scope_seller_ids' => [$seller->id], 'funded_by' => 'seller', 'seller_id' => $seller->id,
        'channels' => ['web'], 'first_order_only' => 1,
    ]))->assertRedirect(route('admin.coupon.index'));

    $coupon = Coupon::query()->where('code', 'DIWALI100')->firstOrFail();

    expect($coupon->scope_ids)->toBe([$seller->id])
        ->and($coupon->seller_id)->toBe($seller->id)
        ->and($coupon->channels)->toBe(['web'])
        ->and($coupon->isFirstOrderOnly())->toBeTrue()
        ->and($coupon->min_cart_value->toDecimal())->toBe('499.00')
        ->and($coupon->created_by)->toBe($admin->id);

    $this->actingAs($admin)->post(route('admin.coupon.store'), couponPayload())->assertRedirect();
    expect(promotionErrors())->toHaveKey('code');

    $this->actingAs($admin)->post(route('admin.coupon.update', [$coupon->id]), couponPayload(['name' => 'Renamed', 'status' => null]))->assertRedirect(route('admin.coupon.index'));
    expect($coupon->fresh())->name->toBe('Renamed')->scope->toBe('all')->scope_ids->toBeNull()->seller_id->toBeNull()
        ->and($coupon->fresh()->isEnabled())->toBeFalse();

    $used = promotionCoupon(['code' => 'USEDONE']);
    placeWithCoupon(orderCustomer(), [cartVariant()], 'USEDONE');

    $this->actingAs($admin)->post(route('admin.coupon.delete', [$used->id]))->assertSessionHas('error', trans('promotion::coupon.messages.in_use'));
    $this->actingAs($admin)->post(route('admin.coupon.delete', [$coupon->id]))->assertRedirect(route('admin.coupon.index'));

    expect(Coupon::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(Coupon::query()->whereKey($coupon->id)->exists())->toBeFalse();
});

test('bulk generation makes unique single-use codes in one batch', function () {
    $admin = promotionAdmin();

    $this->actingAs($admin)->post(route('admin.coupon.bulk_generate'), couponPayload(['code' => null, 'prefix' => 'gift', 'count' => 25]))
        ->assertRedirect(route('admin.coupon.index'));

    $codes = Coupon::query()->where('code', 'like', 'GIFT-%')->get();

    expect($codes)->toHaveCount(25)
        ->and($codes->pluck('code')->unique())->toHaveCount(25)
        ->and($codes->pluck('batch')->unique())->toHaveCount(1)
        ->and($codes->pluck('usage_limit_total')->unique()->all())->toBe([1])
        ->and($codes->every(fn (Coupon $coupon) => preg_match(config('promotion.code_pattern'), $coupon->code) === 1))->toBeTrue();
});

test('flash deals are saved with variants by SKU and refuse prices not below the own price', function () {
    $admin = promotionAdmin();
    $cheap = cartVariant(['price' => '499', 'mrp' => '999']);
    $other = cartVariant(['price' => '299', 'mrp' => '599']);
    $payload = [
        'name' => 'Weekend sale',
        'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'status' => 1,
    ];

    $this->actingAs($admin)->post(route('admin.flash_deal.store'), $payload + ['new_items' => [
        ['sku' => $cheap->sku, 'deal_price' => '199', 'stock_cap' => 10],
        ['sku' => $other->sku, 'deal_price' => '350'],
    ]])->assertRedirect();

    expect(promotionErrors())->toHaveKey('new_items.1.deal_price')
        ->and(FlashDeal::query()->where('name', 'Weekend sale')->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.flash_deal.store'), $payload + ['new_items' => [
        ['sku' => $cheap->sku, 'deal_price' => '199', 'stock_cap' => 10],
        ['sku' => 'NO-SUCH-SKU', 'deal_price' => ''],
        ['sku' => '', 'deal_price' => ''],
    ]])->assertRedirect();
    expect(promotionErrors())->toHaveKey('new_items.1.sku');

    $this->actingAs($admin)->post(route('admin.flash_deal.store'), $payload + ['new_items' => [
        ['sku' => $cheap->sku, 'deal_price' => '199', 'stock_cap' => 10],
    ]])->assertRedirect();

    $deal = FlashDeal::query()->where('name', 'Weekend sale')->firstOrFail();
    $item = $deal->items()->sole();

    expect($item->variant_id)->toBe($cheap->id)
        ->and($item->deal_price->toDecimal())->toBe('199.00')
        ->and($item->stock_cap)->toBe(10);

    $this->actingAs($admin)->post(route('admin.flash_deal.update', [$deal->id]), $payload + [
        'items' => [$item->id => ['deal_price' => '179', 'stock_cap' => '']],
        'new_items' => [['sku' => $other->sku, 'deal_price' => '249']],
    ])->assertRedirect(route('admin.flash_deal.edit', [$deal->id]));

    expect($item->fresh())->deal_price->toEqual(Money::of('179'))->stock_cap->toBeNull()
        ->and($deal->items()->count())->toBe(2);

    $this->actingAs($admin)->post(route('admin.flash_deal.update', [$deal->id]), $payload + ['items' => [$item->id => ['remove' => 1]]])->assertRedirect();
    expect($deal->items()->count())->toBe(1);
});

test('the report shows coupon performance and the AOV uplift', function () {
    promotionCoupon(['code' => 'REPORT40', 'value' => '40']);
    placeWithCoupon(orderCustomer(), [cartVariant(['price' => '1000', 'mrp' => '1000'])], 'REPORT40');
    placeWithCoupon(orderCustomer(), [cartVariant(['price' => '500', 'mrp' => '500'])], null);

    $this->actingAs(promotionAdmin())->get(route('admin.promotion_report.index'))
        ->assertOk()
        ->assertSee('data-report-coupon="REPORT40"', false)
        ->assertSee('₹40.00')
        ->assertSee('AOV ₹960');
});
