<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Models\Customer;

beforeEach(function () {
    // The "seller" guard and the seller.login route come from the Seller module (M03).
    Route::middleware(['web', 'auth:seller'])->get('/_test/supplier-area', fn () => 'supplier area');
    app('router')->getRoutes()->refreshNameLookups();
});

test('the seller guard is configured', function () {
    expect(config('auth.guards.seller'))->toBe(['driver' => 'session', 'provider' => 'sellers'])
        ->and(Route::has('seller.login'))->toBeTrue();
});

test('a guest on a seller route goes to the supplier login', function () {
    $this->get('/_test/supplier-area')->assertRedirect(frontRoute('seller.login'));
});

test('the seller login url follows the seller.login route', function () {
    expect(sellerLoginUrl())->toBe(frontRoute('seller.login'))
        ->and(sellerLoginUrl())->toBe(url('supplier/login'));
});

test('a signed-in customer still needs the seller login for the supplier panel', function () {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get('/_test/supplier-area')
        ->assertRedirect(frontRoute('seller.login'));
});

test('json requests to a seller route get the 401 envelope', function () {
    $this->getJson('/_test/supplier-area')->assertUnauthorized()->assertJsonPath('code', 4011);
});

test('customer guests still go to the customer login', function () {
    $this->get(frontRoute('customer.myaccount'))->assertRedirect(frontRoute('customer.login'));
});

test('admin guests still go to the admin login', function () {
    $this->get(route('admin.dashboard.index'))->assertRedirect(route('backend_login'));
});
