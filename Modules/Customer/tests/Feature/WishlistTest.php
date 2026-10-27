<?php

use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Customer\Support\StorefrontWishlistProvider;
use Modules\Customer\Support\ThemeWishlistProvider;
use Modules\Storefront\Contracts\WishlistStateProvider;
use Modules\Storefront\Services\ProductCardPresenter;
use Modules\Theme\Contracts\WishlistProvider;

require_once __DIR__.'/../Helpers.php';
require_once __DIR__.'/../../../Storefront/tests/Helpers.php';

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->product = storefrontProduct();
});

test('the heart toggles a product in and out of the wishlist', function () {
    $this->actingAs($this->customer, 'customer');

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id])
        ->assertOk()
        ->assertJson(['success' => true, 'wishlisted' => true, 'count' => 1]);

    $this->assertDatabaseHas('customer_wishlist', ['customer_id' => $this->customer->id, 'product_id' => $this->product->id]);

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id])
        ->assertOk()
        ->assertJson(['wishlisted' => false, 'count' => 0]);

    $this->assertDatabaseMissing('customer_wishlist', ['customer_id' => $this->customer->id]);
});

test('an explicit state is idempotent', function () {
    $this->actingAs($this->customer, 'customer');

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id, 'wishlisted' => true])->assertJson(['wishlisted' => true]);
    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id, 'wishlisted' => true])->assertJson(['wishlisted' => true, 'count' => 1]);
    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id, 'wishlisted' => false])->assertJson(['wishlisted' => false, 'count' => 0]);
});

test('guests get a 401 so the page can open the login drawer', function () {
    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id])->assertUnauthorized();

    $this->get(frontRoute('customer.wishlist.index'))->assertRedirect(frontRoute('customer.login'));
});

test('unavailable products cannot be added', function () {
    $this->actingAs($this->customer, 'customer');
    $draft = storefrontProduct();
    storefrontUpdate($draft, ['status' => 'draft']);

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $draft->id])
        ->assertStatus(422)
        ->assertJson(['success' => false, 'wishlisted' => false]);

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => 999999999])->assertStatus(422);
});

test('the wishlist page lists the saved products and can remove one', function () {
    app(WishlistService::class)->set($this->customer, $this->product->id, true);
    $name = $this->product->translations()->value('name');

    $this->actingAs($this->customer, 'customer')
        ->get(frontRoute('customer.wishlist.index'))
        ->assertSuccessful()
        ->assertSee($name)
        ->assertSee(frontRoute('customer.wishlist.destroy', [$this->product->id]), false);

    $this->delete(frontRoute('customer.wishlist.destroy', [$this->product->id]))->assertRedirect(frontRoute('customer.wishlist.index'));

    expect(app(WishlistService::class)->ids($this->customer))->toBe([]);
});

test('an empty wishlist shows the empty state', function () {
    $this->actingAs($this->customer, 'customer')
        ->get(frontRoute('customer.wishlist.index'))
        ->assertSuccessful()
        ->assertSee(trans('customer::customer_front.wishlist.empty'));
});

test('the storefront providers flag wishlisted cards and show the product page heart', function () {
    expect(app(WishlistProvider::class))->toBeInstanceOf(ThemeWishlistProvider::class)
        ->and(app(WishlistStateProvider::class))->toBeInstanceOf(StorefrontWishlistProvider::class)
        ->and(app(WishlistStateProvider::class)->toggleUrl())->toBe(frontRoute('customer.wishlist.toggle'));

    $this->actingAs($this->customer, 'customer');
    app(WishlistService::class)->set($this->customer, $this->product->id, true);

    [$card] = app(ProductCardPresenter::class)->present([$this->product->id]);

    expect($card['wishlisted'])->toBeTrue()
        ->and(app(WishlistStateProvider::class)->isWishlisted($this->product->id))->toBeTrue()
        ->and(app(WishlistProvider::class)->ids())->toBe([$this->product->id]);
});

test('the layout seeds the wishlist store and toggle endpoint', function () {
    app(WishlistService::class)->set($this->customer, $this->product->id, true);

    $this->actingAs($this->customer, 'customer')
        ->get(frontRoute('customer.myaccount'))
        ->assertSee("window.__wishlistIds = JSON.parse('[".$this->product->id."]')", false)
        ->assertSee('window.__wishlistToggleUrl', false)
        ->assertSee('window.__customerAuthenticated = true', false);
});

test('the wishlist has a size limit', function () {
    config(['customer.max_wishlist_items' => 1]);
    $other = storefrontProduct();
    $this->actingAs($this->customer, 'customer');

    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $this->product->id])->assertOk();
    $this->postJson(frontRoute('customer.wishlist.toggle'), ['product_id' => $other->id])
        ->assertStatus(422)
        ->assertJson(['message' => trans('customer::customer_front.wishlist.full', ['max' => 1])]);
});
