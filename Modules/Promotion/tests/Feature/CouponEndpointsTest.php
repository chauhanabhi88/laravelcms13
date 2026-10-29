<?php

use Laravel\Passport\Passport;
use Modules\Cart\Models\Cart;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Promotion\Models\Coupon;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

test('the cart page shows the coupon box, and a form post applies and removes a code', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '499', 'mrp' => '999'])]);
    promotionCoupon(['code' => 'SAVE50', 'value' => '50', 'description' => 'Fifty off your order']);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('cart.index'))
        ->assertSuccessful()
        ->assertSee('data-coupon-box="cart"', false)
        ->assertSee('data-offer="SAVE50"', false)
        ->assertSee('Fifty off your order');

    $this->actingAs($customer, 'customer')
        ->from(frontRoute('cart.index'))
        ->post(frontRoute('cart.coupon.apply'), ['code' => 'save50'])
        ->assertRedirect(frontRoute('cart.index'))
        ->assertSessionHas('coupon_success', 'SAVE50 applied. You save ₹50.');

    expect(customerCart($customer)->coupon_code)->toBe('SAVE50');

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('cart.index'))
        ->assertSee('data-coupon-applied="SAVE50"', false)
        ->assertSee('Coupon (SAVE50)');

    $this->actingAs($customer, 'customer')
        ->from(frontRoute('cart.index'))
        ->delete(frontRoute('cart.coupon.remove'))
        ->assertRedirect(frontRoute('cart.index'));

    expect(customerCart($customer)->coupon_code)->toBeNull();
});

test('a refused code is flashed back to the box', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '100', 'mrp' => '100'])]);

    $this->actingAs($customer, 'customer')
        ->from(frontRoute('cart.index'))
        ->post(frontRoute('cart.coupon.apply'), ['code' => 'NOPE'])
        ->assertRedirect(frontRoute('cart.index'))
        ->assertSessionHas('coupon_error', 'NOPE is not a valid coupon code.');
});

test('JSON requests get the re-rendered cart body or a 422 with the reason', function () {
    $cart = guestCartWith([cartVariant(['price' => '400', 'mrp' => '400'])]);
    promotionCoupon(['code' => 'GUEST20', 'value' => '20']);
    promotionCoupon(['code' => 'BIGONLY', 'min_cart_value' => '1000']);

    $this->withCredentials()->withCookie('cart_token', $cart->token)
        ->postJson(frontRoute('cart.coupon.apply'), ['code' => 'GUEST20', 'view' => 'page'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 1)
        ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Coupon (GUEST20)'))->etc());

    $this->withCredentials()->withCookie('cart_token', $cart->token)
        ->postJson(frontRoute('cart.coupon.apply'), ['code' => 'BIGONLY'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'min_cart');

    $this->withCredentials()->withCookie('cart_token', $cart->token)
        ->getJson(frontRoute('cart.offers'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'GUEST20')
        ->assertJsonPath('data.0.applied', true)
        ->assertJsonPath('data.1.applicable', false);
});

test('the checkout pages show the coupon box with the payment method', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant()]);
    promotionCoupon(['code' => 'CHECK10', 'value' => '10']);
    applyCoupon($customer, 'CHECK10');

    $this->actingAs($customer, 'customer')
        ->withSession([config('order.checkout_session_key') => orderCheckout($customer, 'cod')->toArray()])
        ->get(frontRoute('checkout.summary'))
        ->assertSuccessful()
        ->assertSee('data-coupon-box="checkout"', false)
        ->assertSee('data-coupon-applied="CHECK10"', false)
        ->assertSee('name="expected_total" value="489.00"', false);
});

test('the Buy Now cart takes its own coupon', function () {
    $customer = orderCustomer();
    $variant = cartVariant();
    cartService()->buyNow($customer, $variant->id, 1);
    promotionCoupon(['code' => 'QUICK5', 'value' => '5']);

    $this->actingAs($customer, 'customer')
        ->from(frontRoute('cart.index'))
        ->post(frontRoute('cart.coupon.apply'), ['code' => 'QUICK5', 'cart_type' => Cart::TYPE_BUY_NOW])
        ->assertSessionHas('coupon_success');

    expect(customerCart($customer, Cart::TYPE_BUY_NOW)->coupon_code)->toBe('QUICK5')
        ->and(customerCart($customer))->toBeNull();
});

test('the API applies, lists and removes coupons for a customer token', function () {
    $customer = orderCustomer();
    orderCart($customer, [cartVariant(['price' => '300', 'mrp' => '300'])]);
    promotionCoupon(['code' => 'APP30', 'value' => '30', 'channels' => ['app']]);
    promotionCoupon(['code' => 'WEBONLY', 'value' => '30', 'channels' => ['web']]);
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');
    $headers = ['Authorization' => 'Bearer test'];

    $this->postJson('/api/v1/cart/coupon', ['code' => 'WEBONLY'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('reason', 'channel');

    $this->postJson('/api/v1/cart/coupon', ['code' => 'APP30'], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.cart.coupon_code', 'APP30')
        ->assertJsonPath('data.totals.grand_total', '270.00');

    $this->getJson('/api/v1/cart/offers', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'APP30')
        ->assertJsonPath('data.0.applied', true);

    $this->deleteJson('/api/v1/cart/coupon', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.totals.grand_total', '300.00');

    expect(Coupon::query()->where('code', 'APP30')->value('used_count'))->toBe(0);
});
