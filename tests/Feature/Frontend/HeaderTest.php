<?php

use Modules\Customer\Models\Customer;
use Modules\Pages\Models\Pages;
use Modules\Theme\Contracts\CartCountProvider;
use Modules\Theme\Contracts\MegaMenuProvider;
use Modules\Theme\Support\MegaMenu;

test('the home page renders the header with search, sell link and an empty cart', function () {
    Pages::factory()->create(['slug' => 'sell-with-us', 'title' => 'Sell with Us']);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('role="search"', false)
        ->assertSee('name="q"', false)
        ->assertSee('Sell with Us')
        // The supplier landing (M03) wins over the CMS page fallback once its route exists.
        ->assertSee('href="'.frontRoute('seller.landing').'"', false)
        ->assertSee('Cart, 0 items')
        ->assertSee('window.__cartCount = 0', false);
});

test('the sell link is hidden until its page or route exists', function () {
    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertDontSee('href="'.frontRoute('page', ['sell-with-us']).'"', false);
});

test('guests are offered login and sign up', function () {
    $this->get(frontRoute('homepage'))
        ->assertSee('Sign in to see your orders')
        ->assertSee('href="'.frontRoute('customer.login').'"', false)
        ->assertSee('href="'.frontRoute('customer.signup').'"', false);
});

test('a logged in customer sees their name and account links', function () {
    $customer = Customer::factory()->create(['first_name' => 'Meera']);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('Hi, Meera')
        ->assertSee('href="'.frontRoute('customer.myaccount').'"', false)
        ->assertDontSee('Sign in to see your orders');
});

test('the mobile drawer and bottom navigation are rendered', function () {
    $this->get(frontRoute('homepage'))
        ->assertSee("overlay({ name: 'categories' })", false)
        ->assertSee('Quick navigation')
        ->assertSee("open-drawer', 'categories'", false);
});

test('without a category tree the bar falls back to the configured nav links', function () {
    $this->get(frontRoute('homepage'))
        ->assertSee('aria-label="Categories"', false)
        ->assertSee('href="'.frontRoute('blog.index').'"', false);
});

test('the cart badge and mega-menu use the bound providers', function () {
    app()->instance(CartCountProvider::class, new class implements CartCountProvider
    {
        public function count(): int
        {
            return 3;
        }
    });
    app()->instance(MegaMenuProvider::class, new class implements MegaMenuProvider
    {
        public function tree(int $depth = 3): array
        {
            return [[
                'id' => 1, 'name' => 'Ethnic Wear', 'url' => '/c/ethnic-wear', 'image' => null,
                'children' => [[
                    'id' => 2, 'name' => 'Sarees', 'url' => '/c/sarees', 'image' => null,
                    'children' => [['id' => 3, 'name' => 'Silk Sarees', 'url' => '/c/silk-sarees', 'image' => null, 'children' => []]],
                ]],
            ]];
        }
    });
    app()->forgetInstance(MegaMenu::class);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('Cart, 3 items')
        ->assertSee('window.__cartCount = 3', false)
        ->assertSee('aria-controls="mega-panel-0"', false)
        ->assertSee('Silk Sarees')
        ->assertSee('View all Ethnic Wear')
        ->assertSee('Search in Ethnic Wear', false);
});

test('the mega-menu tree is cached per locale and flushed on demand', function () {
    $provider = new class implements MegaMenuProvider
    {
        public int $calls = 0;

        public function tree(int $depth = 3): array
        {
            $this->calls++;

            return [['id' => 1, 'name' => 'Men', 'url' => '/c/men', 'image' => null, 'children' => []]];
        }
    };
    app()->instance(MegaMenuProvider::class, $provider);

    $menu = app(MegaMenu::class);
    $menu->tree();
    $menu->tree();
    expect($provider->calls)->toBe(1);

    $menu->flush();
    $menu->tree();
    expect($provider->calls)->toBe(2);
});
