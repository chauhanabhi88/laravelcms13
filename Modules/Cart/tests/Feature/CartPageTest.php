<?php

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Seller\Models\Seller;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

test('an empty cart page creates no cart', function () {
    $this->get(frontRoute('cart.index'))
        ->assertSuccessful()
        ->assertSee('Your cart is empty')
        ->assertSee('Continue shopping');

    expect(Cart::query()->count())->toBe(0);
});

test('add to cart over fetch returns the count and the mini-cart, and sets the guest cookie', function () {
    $variant = cartVariant(['price' => '499.00', 'mrp' => '999.00']);

    $response = $this->postJson(frontRoute('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertSuccessful()
        ->assertJson(['success' => true, 'count' => 2, 'message' => 'Added to cart'])
        ->assertCookie('cart_token');

    $cart = Cart::query()->sole();
    expect($response->json('html'))->toContain('View cart')->toContain('₹998')
        ->and($response->getCookie('cart_token', false)?->getValue())->not->toBeEmpty()
        ->and($cart->isGuest())->toBeTrue();
});

test('a plain add to cart post redirects to the cart page', function () {
    $this->post(frontRoute('cart.add'), ['variant_id' => cartVariant()->id])
        ->assertRedirect(frontRoute('cart.index'))
        ->assertSessionHas('success', 'Added to cart');
});

test('add to cart explains what went wrong', function () {
    $this->postJson(frontRoute('cart.add'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variant_id' => 'Please choose a size first.']);

    $this->postJson(frontRoute('cart.add'), ['variant_id' => cartVariant(['stock' => 0])->id])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Sorry, this item is out of stock.']);
});

test('the cart page shows seller groups, notices, the price summary and saved items', function () {
    $seller = Seller::factory()->active()->create(['store_name' => 'Meera Weaves']);
    $customer = Customer::factory()->create();
    $kept = cartVariant(['price' => '499.00', 'mrp' => '999.00'], $seller);
    $saved = cartVariant(['price' => '250.00', 'mrp' => '250.00'], $seller);
    $soldOut = cartVariant(['price' => '300.00', 'mrp' => '300.00']);
    $cart = cartService()->add(customerOwner($customer), $kept->id, 2)->cart;
    cartService()->add(customerOwner($customer), $soldOut->id, 1);
    $savedItem = cartService()->add(customerOwner($customer), $saved->id, 1)->item;
    cartService()->saveForLater($cart, $savedItem);
    cartUpdateVariant($soldOut, ['stock' => 0]);
    cartUpdateVariant($kept, ['price' => '449.00']);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('cart.index'))
        ->assertSuccessful()
        ->assertSee('Sold by Meera Weaves')
        ->assertSee('Good news! Price dropped from ₹499 to ₹449.')
        ->assertSee('Not available')
        ->assertSee('Out of stock')
        ->assertSee('Saved for later (1)')
        ->assertSee('Price details')
        ->assertSee('₹1,998')
        ->assertSee('₹898')
        ->assertSee('FREE')
        ->assertSee('You will save ₹1,100 on this order')
        ->assertSee('Remove or save the unavailable items to continue.')
        ->assertSee('window.__cartCount = 3', false);
});

test('page actions answer with the re-rendered body', function () {
    $result = cartService()->add(guestOwner(), cartVariant()->id, 1);

    $response = $this->withCredentials()->withCookie('cart_token', $result->cart->token)
        ->postJson(frontRoute('cart.save_for_later', [$result->item->id]), ['view' => 'page'])
        ->assertSuccessful()
        ->assertJson(['count' => 0, 'message' => 'Saved for later']);

    expect($response->json('html'))->toContain('Saved for later (1)')->toContain('Your cart is empty');
});

test('the drawer and count endpoints read the visitor cart', function () {
    $cart = guestCartWith([[cartVariant(), 3]]);

    $this->withCredentials()->withCookie('cart_token', $cart->token)->getJson(frontRoute('cart.count'))->assertExactJson(['count' => 3]);
    expect($this->withCredentials()->withCookie('cart_token', $cart->token)->getJson(frontRoute('cart.drawer'))->assertSuccessful()->json('html'))
        ->toContain('View cart');
});

test('the pincode is stored on the cart', function () {
    $cart = guestCartWith([cartVariant()]);

    $this->withCredentials()->withCookie('cart_token', $cart->token)
        ->postJson(frontRoute('cart.pincode'), ['pincode' => '560001'])
        ->assertSuccessful();
    $this->withCredentials()->postJson(frontRoute('cart.pincode'), ['pincode' => '012345'])->assertJsonValidationErrors('pincode');

    expect($cart->fresh()->delivery_pincode)->toBe('560001');
});

test('guests are asked to log in to move a line to the wishlist', function () {
    $result = cartService()->add(guestOwner(), cartVariant()->id, 1);

    $this->withCredentials()->withCookie('cart_token', $result->cart->token)
        ->postJson(frontRoute('cart.move_to_wishlist', [$result->item->id]))
        ->assertUnauthorized()
        ->assertJson(['login' => true]);
});

test('the wishlist page moves a single-variant product into the cart', function () {
    $customer = Customer::factory()->create();
    $variant = cartVariant();
    app(WishlistService::class)->set($customer, $variant->product_id, true);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.wishlist.index'))
        ->assertSee(frontRoute('cart.wishlist.move'), false);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('cart.wishlist.move'), ['product_id' => $variant->product_id])
        ->assertRedirect(frontRoute('cart.index'));

    expect(cartService()->count(customerOwner($customer)))->toBe(1)
        ->and(app(WishlistService::class)->has($customer, $variant->product_id))->toBeFalse();
});

test('the product page posts add to cart and buy now to the cart', function () {
    ['product' => $product] = pdpProduct();

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee('action="'.frontRoute('cart.add').'"', false)
        ->assertSee('formaction="'.frontRoute('cart.buy_now').'"', false)
        ->assertSee('data-cart-add', false)
        ->assertSee('overlay-mini-cart', false);
});

test('the header badge counts the cart units', function () {
    $customer = Customer::factory()->create();
    cartService()->add(customerOwner($customer), cartVariant()->id, 2);
    $line = cartService()->add(customerOwner($customer), cartVariant()->id, 1);
    cartService()->saveForLater($line->cart, $line->item);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('homepage'))
        ->assertSee('window.__cartCount = 2', false);

    expect(CartItem::query()->count())->toBe(2);
});
