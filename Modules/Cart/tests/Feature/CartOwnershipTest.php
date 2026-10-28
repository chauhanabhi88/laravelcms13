<?php

use Modules\Cart\Models\CartItem;
use Modules\Customer\Models\Customer;

require_once __DIR__.'/../Helpers.php';

test('a visitor cannot change a line of another cart', function (string $action) {
    $theirs = cartService()->add(guestOwner(), cartVariant()->id, 1);
    $mine = guestCartWith([cartVariant()]);
    $item = $theirs->item;

    $request = $this->withCredentials()->withCookie('cart_token', $mine->token);

    $response = match ($action) {
        'update' => $request->patchJson(frontRoute('cart.update', [$item->id]), ['quantity' => 3]),
        'remove' => $request->deleteJson(frontRoute('cart.remove', [$item->id])),
        'save for later' => $request->postJson(frontRoute('cart.save_for_later', [$item->id])),
    };

    $response->assertNotFound();
    expect($item->fresh()->quantity)->toBe(1)
        ->and($item->fresh()->isSavedForLater())->toBeFalse();
})->with(['update', 'remove', 'save for later']);

test('a guest without a cart gets 404 on item routes', function () {
    $item = cartService()->add(guestOwner(), cartVariant()->id, 1)->item;

    $this->deleteJson(frontRoute('cart.remove', [$item->id]))->assertNotFound();

    expect(CartItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

test('a customer cannot reach a guest cart line', function () {
    $item = cartService()->add(guestOwner(), cartVariant()->id, 1)->item;
    cartService()->add(customerOwner($customer = Customer::factory()->create()), cartVariant()->id, 1);

    $this->actingAs($customer, 'customer')
        ->patchJson(frontRoute('cart.update', [$item->id]), ['quantity' => 2])
        ->assertNotFound();
});

test('the owner can change their own line', function () {
    $result = cartService()->add(guestOwner(), cartVariant()->id, 1);

    $this->withCredentials()->withCookie('cart_token', $result->cart->token)
        ->patchJson(frontRoute('cart.update', [$result->item->id]), ['quantity' => 3])
        ->assertSuccessful()
        ->assertJson(['success' => true, 'count' => 3]);
});
