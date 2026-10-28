<?php

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Support\CartUrls;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';

test('buy now uses a separate cart and leaves the standard cart untouched', function () {
    $customer = Customer::factory()->create();
    $inCart = cartVariant();
    $standard = cartService()->add(customerOwner($customer), $inCart->id, 2)->cart;
    $buyNow = cartVariant();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('cart.buy_now'), ['variant_id' => $buyNow->id, 'quantity' => 1])
        ->assertRedirect(CartUrls::afterBuyNow());

    $buyNowCart = Cart::query()->where('customer_id', $customer->id)->buyNow()->firstOrFail();

    expect($buyNowCart->id)->not->toBe($standard->id)
        ->and(CartItem::query()->where('cart_id', $buyNowCart->id)->pluck('variant_id')->all())->toBe([$buyNow->id])
        ->and(CartItem::query()->where('cart_id', $standard->id)->pluck('quantity', 'variant_id')->all())->toEqual([$inCart->id => 2]);
});

test('a second buy now replaces the previous buy-now line', function () {
    $customer = Customer::factory()->create();
    cartService()->buyNow($customer, cartVariant()->id, 1);
    $latest = cartVariant();

    $result = cartService()->buyNow($customer, $latest->id, 2);

    expect(CartItem::query()->where('cart_id', $result->cart->id)->get(['variant_id', 'quantity'])->toArray())
        ->toBe([['variant_id' => $latest->id, 'quantity' => 2]]);
});

test('guests are sent to log in before buying now', function () {
    $variant = cartVariant();

    $this->from('/p/some-product-1')
        ->post(frontRoute('cart.buy_now'), ['variant_id' => $variant->id])
        ->assertRedirect(frontRoute('customer.login').'?'.http_build_query(['redirect' => url('/p/some-product-1')]));

    $this->postJson(frontRoute('cart.buy_now'), ['variant_id' => $variant->id])
        ->assertUnauthorized()
        ->assertJson(['login' => true]);

    expect(Cart::query()->count())->toBe(0);
});

test('the buy-now cart page shows only the buy-now line', function () {
    $customer = Customer::factory()->create();
    cartService()->add(customerOwner($customer), cartVariant(product: [])->id, 1);
    $variant = cartVariant();
    cartService()->buyNow($customer, $variant->id, 1);
    $name = $variant->product()->with('translations')->first()->name;

    $this->actingAs($customer, 'customer')
        ->get(CartUrls::cart(Cart::TYPE_BUY_NOW))
        ->assertSuccessful()
        ->assertSee('Only this item is in your Buy Now order.')
        ->assertSee($name)
        ->assertSee('1 item in your cart');
});

test('an expired buy-now cart is discarded', function () {
    $customer = Customer::factory()->create();
    $cart = cartService()->buyNow($customer, cartVariant()->id, 1)->cart;
    Cart::query()->whereKey($cart->id)->update(['last_activity_at' => now()->subMinutes(61)]);

    expect(cartService()->resolve(customerOwner($customer), false, Cart::TYPE_BUY_NOW))->toBeNull()
        ->and(Cart::query()->whereKey($cart->id)->exists())->toBeFalse();
});
