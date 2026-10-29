<?php

use Modules\Customer\Models\CustomerWishlist;
use Modules\Order\Models\SalesOrder;

require_once __DIR__.'/Helpers.php';

const API_MONEY_KEYS = [
    'price', 'mrp', 'line_total', 'subtotal', 'total_mrp', 'discount', 'delivery_charge', 'grand_total', 'you_save',
    'coupon_discount', 'cod_fee', 'reseller_margin', 'refund_amount', 'amount', 'customer_price', 'unit_refund',
    'minimum_withdrawal', 'available', 'pending', 'paid',
];

function apiAssertMoney(array $payload): void
{
    $values = apiMoneyValues($payload, API_MONEY_KEYS);

    expect($values)->not->toBeEmpty();

    foreach ($values as $path => $value) {
        if ($value === null) {
            continue;
        }

        expect($value)->toBeString("{$path} should be a decimal string")
            ->and($value)->toMatch('/^-?\d+\.\d{2}$/', "{$path} should have two decimals");
    }
}

beforeEach(function () {
    apiSetup();
});

it('sends product card prices as two-place decimal strings', function () {
    cartVariant(['price' => '499', 'mrp' => '999', 'stock' => 5]);

    apiAssertMoney($this->getJson(route('storefront_api.search'))->assertOk()->json());
});

it('sends the cart lines and totals as two-place decimal strings', function () {
    $customer = apiCustomer();
    orderCart($customer, [[cartVariant(['price' => '249.5', 'mrp' => '999', 'stock' => 9]), 2]]);

    // The cart API reads the customer from the bearer header (optional auth), so send one next to actingAs.
    $payload = $this->getJson(route('cart_api.show'), ['Authorization' => 'Bearer test'])->assertOk()->json();

    apiAssertMoney($payload);
    expect($payload['data']['totals']['grand_total'])->toBe('499.00');
});

it('sends order amounts as two-place decimal strings', function () {
    $customer = apiCustomer();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    apiAssertMoney($this->getJson(route('order_api.orders.index'))->assertOk()->json());
    apiAssertMoney($this->getJson(route('order_api.orders.show', ['orderNo' => $order->order_no]))->assertOk()->json());
});

it('sends checkout summary and place amounts as two-place decimal strings', function () {
    $customer = apiCustomer();
    orderCart($customer, [cartVariant(['price' => '499', 'stock' => 5])]);
    $addressId = $customer->address()->value('id');

    apiAssertMoney($this->postJson(route('order_api.checkout.summary'), ['address_id' => $addressId, 'payment_method' => 'cod'])->assertOk()->json());
});

it('sends wishlist prices as strings, not floats', function () {
    $customer = apiCustomer();
    $variant = cartVariant(['price' => '349', 'mrp' => '699', 'stock' => 5]);
    CustomerWishlist::query()->create(['customer_id' => $customer->id, 'product_id' => $variant->product_id]);

    $payload = $this->getJson(route('customer_api.wishlist.index'))->assertOk()->json();

    expect($payload['data'][0]['price'])->toBe('349.00')
        ->and($payload['data'][0]['mrp'])->toBe('699.00');
});

it('sends reseller earnings as two-place decimal strings', function () {
    apiCustomer(orderCustomer(reseller: true));

    apiAssertMoney($this->getJson(route('finance_api.earnings.show'))->assertOk()->json());
});

it('sends timestamps as ISO-8601 with an offset', function () {
    $customer = apiCustomer();
    SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    $placedAt = $this->getJson(route('order_api.orders.index'))->assertOk()->json('data.0.placed_at');

    expect($placedAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});
