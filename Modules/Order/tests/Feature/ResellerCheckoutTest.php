<?php

use Illuminate\Validation\ValidationException;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Services\CheckoutService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
    $this->endPincode = orderPincode(orderState('Tamil Nadu'));
    $this->endCustomer = [
        'name' => 'Meena Iyer',
        'phone' => '9876543210',
        'address_line1' => '4, Temple Street',
        'address_line2' => 'Mylapore',
        'pincode' => $this->endPincode->pincode,
    ];
});

function resellerSession($test, $customer, string $method, array $prices): CheckoutSession
{
    $checkout = app(CheckoutService::class);
    $snapshot = $checkout->start($customer, 'standard');

    return $checkout->setReseller(orderCheckout($customer, $method), $customer, $snapshot, true, $test->endCustomer, $prices);
}

test('a reseller COD order ships to the end customer and collects the customer prices', function () {
    $a = cartVariant(['price' => '400', 'mrp' => '800']);
    $b = cartVariant(['price' => '200', 'mrp' => '300']);
    $reseller = orderCustomer(reseller: true);
    orderCart($reseller, [[$a, 2], $b]);

    $order = placeOrder($reseller, resellerSession($this, $reseller, CheckoutSession::METHOD_COD, [$a->id => '550', $b->id => '200']))->order->fresh();

    expect($order->isResellerOrder())->toBeTrue()
        ->and($order->reseller_margin_total->toDecimal())->toBe('300.00')
        ->and($order->grand_total->toDecimal())->toBe('1300.00');

    $items = SalesOrderItem::query()->where('sales_order_id', $order->id)->get()->keyBy('variant_id');
    expect($items[$a->id]->customer_price->toDecimal())->toBe('550.00')
        ->and($items[$a->id]->reseller_margin->toDecimal())->toBe('300.00')
        ->and($items[$b->id]->reseller_margin->isZero())->toBeTrue();

    $shipping = SalesOrderAddress::query()->where('sales_order_id', $order->id)->where('type', 'shipping')->firstOrFail();
    $billing = SalesOrderAddress::query()->where('sales_order_id', $order->id)->where('type', 'billing')->firstOrFail();

    expect($shipping->name)->toBe('Meena Iyer')
        ->and($shipping->pincode)->toBe($this->endPincode->pincode)
        ->and($shipping->state_id)->toBe($this->endPincode->state_id)
        ->and($shipping->isEndCustomer())->toBeTrue()
        ->and($shipping->phone)->toBe('+919876543210')
        ->and($billing->isEndCustomer())->toBeFalse()
        ->and($billing->name)->not->toBe('Meena Iyer');
});

test('a prepaid reseller order charges the selling price and records no collectable margin', function () {
    $variant = cartVariant(['price' => '400', 'mrp' => '800']);
    $reseller = orderCustomer(reseller: true);
    orderCart($reseller, [$variant]);

    $result = placeOrder($reseller, resellerSession($this, $reseller, CheckoutSession::METHOD_PREPAID, [$variant->id => '600']));
    $order = $result->order->fresh();

    expect($order->grand_total->toDecimal())->toBe('400.00')
        ->and($order->reseller_margin_total->isZero())->toBeTrue()
        ->and($result->payment->amount->toDecimal())->toBe('400.00')
        ->and(SalesOrderItem::query()->where('sales_order_id', $order->id)->firstOrFail()->customer_price->toDecimal())->toBe('600.00')
        // Switching to COD later would collect the customer's price.
        ->and($order->payableAmount('cod')->toDecimal())->toBe('600.00');
});

test('a customer price below the selling price or above the cap is rejected', function (string $price, string $message) {
    orderSettings(['reseller_max_markup_multiplier' => '1.5']);
    $variant = cartVariant(['price' => '400', 'mrp' => '800']);
    $reseller = orderCustomer(reseller: true);
    orderCart($reseller, [$variant]);

    try {
        resellerSession($this, $reseller, CheckoutSession::METHOD_COD, [$variant->id => $price]);
        $this->fail('The price should have been rejected.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('prices.'.$variant->id)
            ->and($e->errors()['prices.'.$variant->id][0])->toContain($message);
    }
})->with([
    'below the selling price' => ['399', '₹400'],
    'above MRP × 1.5' => ['1200.01', '₹1,200'],
]);

test('only resellers can sell to their own customers', function () {
    $variant = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$variant]);

    expect(fn () => resellerSession($this, $customer, CheckoutSession::METHOD_COD, []))->toThrow(ValidationException::class);
});

test('the end customer needs a known pincode and a valid phone', function () {
    $variant = cartVariant();
    $reseller = orderCustomer(reseller: true);
    orderCart($reseller, [$variant]);
    $this->endCustomer = ['name' => 'X', 'phone' => '12', 'address_line1' => 'Somewhere', 'pincode' => '999999'];

    try {
        resellerSession($this, $reseller, CheckoutSession::METHOD_COD, []);
        $this->fail('The end customer should have been rejected.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('end_customer.phone');
    }
});
