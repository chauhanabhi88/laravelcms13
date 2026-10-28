<?php

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Customer/tests/Helpers.php';

const CART_MERGE_PHONE = '+919812300011';

beforeEach(function () {
    customerFakeSms();
});

test('guest lines are merged into the customer cart on OTP login and the cookie is cleared', function () {
    $customer = Customer::factory()->withPhone(CART_MERGE_PHONE)->create();
    $shared = cartVariant(['stock' => 20]);
    $guestOnly = cartVariant();
    $customerCart = cartService()->add(customerOwner($customer), $shared->id, 2)->cart;
    $guest = guestCartWith([[$shared, 3], $guestOnly]);

    $this->withCookie('cart_token', $guest->token)
        ->withSession(customerOtpSession(CART_MERGE_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => str_split(customerLoginCode(CART_MERGE_PHONE))])
        ->assertRedirect()
        ->assertCookieExpired('cart_token');

    $this->assertAuthenticatedAs($customer, 'customer');
    expect(Cart::query()->whereKey($guest->id)->exists())->toBeFalse()
        ->and(CartItem::query()->where('cart_id', $customerCart->id)->pluck('quantity', 'variant_id')->all())
        ->toEqual([$shared->id => 5, $guestOnly->id => 1]);
});

test('merged quantities are capped at the per-line maximum', function () {
    $customer = Customer::factory()->create();
    $variant = cartVariant(['stock' => 50]);
    $customerCart = cartService()->add(customerOwner($customer), $variant->id, 7)->cart;
    $guest = guestCartWith([[$variant, 6]]);

    cartService()->merge($guest, $customer);

    expect(CartItem::query()->where('cart_id', $customerCart->id)->value('quantity'))->toBe(10);
});

test('a customer without a cart adopts the guest cart', function () {
    $customer = Customer::factory()->create();
    $guest = guestCartWith([cartVariant()]);
    $token = $guest->token;

    $merged = cartService()->merge($guest, $customer);

    expect($merged->id)->toBe($guest->id)
        ->and($merged->customer_id)->toBe($customer->id)
        ->and($merged->token)->not->toBe($token)
        ->and(cartService()->count(customerOwner($customer)))->toBe(1);
});

test('a login without a guest cart leaves the customer cart alone', function () {
    $customer = Customer::factory()->withPhone(CART_MERGE_PHONE)->create();
    $cart = cartService()->add(customerOwner($customer), cartVariant()->id, 1)->cart;

    $this->withSession(customerOtpSession(CART_MERGE_PHONE))
        ->post(frontRoute('customer.otp.verify'), ['code' => str_split(customerLoginCode(CART_MERGE_PHONE))])
        ->assertRedirect();

    expect(Cart::query()->count())->toBe(1)
        ->and(cartService()->count(customerOwner($customer)))->toBe(1)
        ->and($cart->fresh())->not->toBeNull();
});
