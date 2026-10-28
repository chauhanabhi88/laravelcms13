<?php

use Modules\Cart\Exceptions\CartException;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Product\Enums\ProductStatus;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

test('a guest visitor has no cart until the first add', function () {
    $variant = cartVariant();

    expect(cartService()->resolve(guestOwner()))->toBeNull()
        ->and(cartService()->count(guestOwner()))->toBe(0)
        ->and(Cart::query()->count())->toBe(0);

    $result = cartService()->add(guestOwner(), $variant->id, 2);

    expect($result->created)->toBeTrue()
        ->and($result->cart->isGuest())->toBeTrue()
        ->and($result->cart->token)->toHaveLength(40)
        ->and($result->item->quantity)->toBe(2)
        ->and($result->item->seller_id)->toBe($variant->seller_id)
        ->and($result->item->price_at_add->equals($variant->price))->toBeTrue()
        ->and(cartService()->count(guestOwner($result->cart->token)))->toBe(2);
});

test('a customer cart is created on demand and reused', function () {
    $customer = Customer::factory()->create();
    $first = cartService()->add(customerOwner($customer), cartVariant()->id, 1)->cart;
    $second = cartService()->add(customerOwner($customer), cartVariant()->id, 1)->cart;

    expect($second->id)->toBe($first->id)
        ->and($first->customer_id)->toBe($customer->id)
        ->and(CartItem::query()->where('cart_id', $first->id)->count())->toBe(2);
});

test('adding a variant already in the cart increments its quantity', function () {
    $variant = cartVariant();
    $cart = cartService()->add(guestOwner(), $variant->id, 2)->cart;
    $result = cartService()->add(guestOwner($cart->token), $variant->id, 3);

    expect($result->created)->toBeFalse()
        ->and($result->capped)->toBeFalse()
        ->and($result->item->quantity)->toBe(5)
        ->and(CartItem::query()->where('cart_id', $cart->id)->count())->toBe(1);
});

test('quantities are capped at the per-line maximum', function () {
    $variant = cartVariant(['stock' => 50]);
    $cart = cartService()->add(guestOwner(), $variant->id, 8)->cart;
    $result = cartService()->add(guestOwner($cart->token), $variant->id, 5);

    expect($result->capped)->toBeTrue()
        ->and($result->item->quantity)->toBe(10);
});

test('quantities are capped at the units available', function () {
    $variant = cartVariant(['stock' => 5, 'reserved' => 2]);
    $result = cartService()->add(guestOwner(), $variant->id, 6);

    expect($result->capped)->toBeTrue()
        ->and($result->item->quantity)->toBe(3);
});

test('the per-line maximum comes from the cart settings', function () {
    cartSettings(['max_qty_per_line' => 3]);

    expect(fn () => cartService()->add(guestOwner(), cartVariant()->id, 4))
        ->toThrow(CartException::class, 'Choose a quantity between 1 and 3.');
});

test('products that cannot be sold are rejected', function (string $case) {
    $variant = match ($case) {
        'paused product' => cartVariant(product: ['status' => ProductStatus::Paused]),
        'suspended seller' => cartVariant(seller: Seller::factory()->suspended()->create()),
        'disabled variant' => cartVariant(['status' => config('core.disabled')]),
        'out of stock' => cartVariant(['stock' => 0]),
    };

    expect(fn () => cartService()->add(guestOwner(), $variant->id, 1))->toThrow(CartException::class)
        ->and(Cart::query()->count())->toBe(0);
})->with(['paused product', 'suspended seller', 'disabled variant', 'out of stock']);

test('a cart holds a limited number of distinct lines', function () {
    cartSettings(['max_lines' => 2]);
    $cart = guestCartWith([cartVariant(), cartVariant()]);

    expect(fn () => cartService()->add(guestOwner($cart->token), cartVariant()->id, 1))
        ->toThrow(CartException::class, 'Your cart can hold up to 2 different items.');
});

test('update sets the quantity and caps it at the units available', function () {
    $variant = cartVariant(['stock' => 4]);
    $result = cartService()->add(guestOwner(), $variant->id, 1);

    $updated = cartService()->update($result->cart, $result->item, 7);

    expect($updated->capped)->toBeTrue()
        ->and($result->item->fresh()->quantity)->toBe(4);
});

test('save for later and move back to cart', function () {
    $variant = cartVariant();
    $result = cartService()->add(guestOwner(), $variant->id, 2);

    cartService()->saveForLater($result->cart, $result->item);
    expect($result->item->fresh()->isSavedForLater())->toBeTrue()
        ->and(cartService()->count(guestOwner($result->cart->token)))->toBe(0);

    $moved = cartService()->moveToCart($result->cart, $result->item->fresh());

    expect($moved->item->quantity)->toBe(2)
        ->and($moved->item->isSavedForLater())->toBeFalse()
        ->and(CartItem::query()->where('cart_id', $result->cart->id)->savedForLater()->count())->toBe(0);
});

test('adding a variant that was saved for later moves it into the cart', function () {
    $variant = cartVariant();
    $result = cartService()->add(guestOwner(), $variant->id, 1);
    cartService()->saveForLater($result->cart, $result->item);

    cartService()->add(guestOwner($result->cart->token), $variant->id, 1);

    expect(CartItem::query()->where('cart_id', $result->cart->id)->get())
        ->toHaveCount(1)
        ->first()->isSavedForLater()->toBeFalse();
});

test('move to wishlist puts the product in the wishlist and removes the line', function () {
    $customer = Customer::factory()->create();
    $variant = cartVariant();
    $result = cartService()->add(customerOwner($customer), $variant->id, 1);

    cartService()->moveToWishlist($result->cart, $result->item, $customer);

    expect(CartItem::query()->whereKey($result->item->id)->exists())->toBeFalse()
        ->and(app(WishlistService::class)->has($customer, $variant->product_id))->toBeTrue();
});
