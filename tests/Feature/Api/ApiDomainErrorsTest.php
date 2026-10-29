<?php

use Illuminate\Support\Str;
use Modules\Core\Contracts\ApiError;
use Modules\Core\Support\Api\ApiCodes;
use Modules\Core\Support\Api\ApiResponse;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Payment\Exceptions\PaymentException;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    apiSetup();
});

it('answers a changed total at Place Order with 4091', function () {
    $customer = apiCustomer();
    orderCart($customer, [cartVariant(['price' => '499', 'stock' => 5])]);

    $this->postJson(route('order_api.checkout.place'), [
        'address_id' => $customer->address()->value('id'),
        'payment_method' => 'cod',
        'token' => (string) Str::uuid(),
        'expected_total' => '1.00',
    ])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 4091)
        ->assertJsonPath('errors.reason', 'price_changed')
        ->assertJsonPath('errors.step', 'summary');
});

it('answers an out-of-stock cart add with 4092', function () {
    apiCustomer();

    $this->postJson(route('cart_api.items.store'), ['variant_id' => cartVariant(['stock' => 0])->id])
        ->assertStatus(409)
        ->assertJsonPath('code', 4092)
        ->assertJsonPath('errors.reason', 'out_of_stock');
});

it('answers COD on a pincode that does not allow it with 4221', function () {
    $customer = apiCustomer(orderCustomer(orderPincode(codAllowed: false)));
    orderCart($customer, [cartVariant()]);

    $this->postJson(route('order_api.checkout.place'), [
        'address_id' => $customer->address()->value('id'),
        'payment_method' => 'cod',
        'token' => (string) Str::uuid(),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 4221)
        ->assertJsonPath('errors.reason', 'cod_pincode')
        ->assertJsonPath('errors.step', 'payment');
});

it('answers other checkout failures as a plain 422 with the reason', function () {
    $customer = apiCustomer();

    $this->postJson(route('order_api.checkout.summary'), ['address_id' => $customer->address()->value('id')])
        ->assertUnprocessable()
        ->assertJsonPath('code', 422)
        ->assertJsonPath('errors.reason', 'cart_not_ready')
        ->assertJsonPath('errors.step', 'cart');
});

it('maps domain reasons to their codes and statuses', function (string $reason, ?int $code, int $http) {
    expect(ApiCodes::forReason($reason))->toBe($code);

    if ($code !== null) {
        expect(ApiCodes::httpStatus($code))->toBe($http);
        expect(ApiResponse::reason($reason, 'm')->getStatusCode())->toBe($http);
    }
})->with([
    'price changed' => ['price_changed', 4091, 409],
    'out of stock' => ['out_of_stock', 4092, 409],
    'cod pincode' => ['cod_pincode', 4221, 422],
    'cod over the limit' => ['cod_limit', 4221, 422],
    'seller suspended' => ['seller_suspended', 4031, 403],
    'unknown reason' => ['something_else', null, 422],
]);

it('renders any ApiError exception thrown under /api as the envelope', function () {
    expect(new PriceChanged)->toBeInstanceOf(ApiError::class)
        ->and(CheckoutException::outOfStock()->apiReason())->toBe('out_of_stock')
        ->and((new PaymentException('nope', 'not_payable'))->apiReason())->toBe('not_payable');

    Route::middleware('api')->get('/api/v1/__probe_price_changed', fn () => throw new PriceChanged);
    Route::middleware('api')->get('/api/v1/__probe_boom', fn () => throw new RuntimeException('boom'));

    $this->getJson('/api/v1/__probe_price_changed')
        ->assertStatus(409)
        ->assertJsonPath('code', 4091);

    $this->getJson('/api/v1/__probe_boom')
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 500);
});

it('lists the app-specific codes in the catalogue', function () {
    expect(ApiCodes::names())->toBe([
        4011 => 'token_invalid',
        4012 => 'token_revoked',
        4031 => 'seller_suspended',
        4091 => 'price_changed',
        4092 => 'out_of_stock',
        4221 => 'cod_not_eligible',
        4291 => 'otp_rate_limited',
    ]);

    foreach (array_keys(ApiCodes::names()) as $code) {
        expect(ApiCodes::message($code))->toBeString()->not->toBe('');
    }
});
