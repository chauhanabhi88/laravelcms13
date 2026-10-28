<?php

use Modules\Cart\Models\Cart;
use Modules\Cart\Support\CartUrls;
use Modules\Customer\Models\CustomerAddress;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\CheckoutService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

/**
 * Sessions do not carry over between test requests, so each step posts with the session the
 * previous step stored.
 */
function checkoutSessionData($customer, array $overrides = []): array
{
    return [config('order.checkout_session_key') => orderCheckout($customer, 'cod', $overrides)->toArray()];
}

test('the cart Continue button and Buy Now now lead to checkout', function () {
    expect(CartUrls::checkout())->toBe(frontRoute('checkout.index'))
        ->and(CartUrls::afterBuyNow())->toBe(frontRoute('checkout.index').'?cart=buy_now');
});

test('checkout with an empty cart goes back to the cart', function () {
    $customer = orderCustomer();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('checkout.index'))
        ->assertRedirect(CartUrls::cart())
        ->assertSessionHas('error', trans('order::front.errors.cart_not_ready'));
});

test('a cart with an unavailable line cannot start checkout', function () {
    $variant = cartVariant(['stock' => 3]);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    cartUpdateVariant($variant, ['stock' => 0]);

    $this->actingAs($customer, 'customer')->get(frontRoute('checkout.index'))->assertRedirect(CartUrls::cart());
});

test('the buyer walks through address, payment and summary and places a COD order', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $address = CustomerAddress::query()->where('customer_id', $customer->id)->firstOrFail();
    $name = $variant->product()->with('translations')->first()->name;

    $this->actingAs($customer, 'customer')->get(frontRoute('checkout.index'))->assertRedirect(frontRoute('checkout.address'));

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('checkout.address'))
        ->assertSuccessful()
        ->assertSee(trans('order::front.address.heading'))
        ->assertSee($address->name)
        ->assertSee($name)
        ->assertDontSee('data-reseller-block', false);

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer, ['address_id' => null, 'payment_method' => null]))
        ->post(frontRoute('checkout.address.save'), ['address_id' => $address->id])
        ->assertRedirect(frontRoute('checkout.payment'))
        ->assertSessionHas(config('order.checkout_session_key').'.address_id', $address->id);

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer, ['payment_method' => null]))
        ->get(frontRoute('checkout.payment'))
        ->assertSuccessful()
        ->assertSee(trans('order::front.payment.cod'));

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer, ['payment_method' => null]))
        ->post(frontRoute('checkout.payment.save'), ['payment_method' => 'cod'])
        ->assertRedirect(frontRoute('checkout.summary'));

    $session = checkoutSessionData($customer);
    $token = $session[config('order.checkout_session_key')]['token'];

    $this->actingAs($customer, 'customer')
        ->withSession($session)
        ->get(frontRoute('checkout.summary'))
        ->assertSuccessful()
        ->assertSee(trans('order::front.summary.place_order'))
        ->assertSee('name="token" value="'.$token.'"', false)
        ->assertSee('name="expected_total" value="499.00"', false);

    $response = $this->actingAs($customer, 'customer')
        ->withSession($session)
        ->post(frontRoute('checkout.place'), ['token' => $token, 'expected_total' => '499.00']);

    $order = SalesOrder::query()->where('placed_token', $token)->firstOrFail();
    $response->assertRedirect($order->confirmationUrl())->assertSessionMissing(config('order.checkout_session_key'));

    $this->actingAs($customer, 'customer')
        ->get($order->confirmationUrl())
        ->assertSuccessful()
        ->assertSee($order->order_no)
        ->assertSee(trans('order::front.success.cod_ready', ['amount' => '₹499']));
});

test('a new address can be added from checkout', function () {
    $variant = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $pincode = orderPincode();

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer))
        ->post(frontRoute('checkout.address.save'), ['address' => [
            'pincode' => $pincode->pincode,
            'name' => 'Ravi Kumar',
            'phone' => '9876501234',
            'address_line1' => '22, Green Park',
            'address_line2' => 'Ring Road',
            'tag' => 'work',
        ]])
        ->assertRedirect(frontRoute('checkout.payment'));

    expect(CustomerAddress::query()->where('customer_id', $customer->id)->where('pincode', $pincode->pincode)->value('name'))->toBe('Ravi Kumar');
});

test('another customer\'s address cannot be chosen', function () {
    $variant = cartVariant();
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $foreign = orderCustomer()->address()->firstOrFail();

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer))
        ->post(frontRoute('checkout.address.save'), ['address_id' => $foreign->id])
        ->assertRedirect(frontRoute('checkout.address'))
        ->assertSessionHas('error', trans('order::front.errors.address_required'));
});

test('COD shows why it is unavailable and cannot be chosen', function () {
    $variant = cartVariant();
    $customer = orderCustomer(orderPincode(codAllowed: false));
    orderCart($customer, [$variant]);

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer, ['payment_method' => null]))
        ->get(frontRoute('checkout.payment'))
        ->assertSuccessful()
        ->assertSee(trans('order::front.payment.cod_unavailable'))
        ->assertSee('data-cod-reason', false);

    $this->actingAs($customer, 'customer')
        ->withSession(checkoutSessionData($customer, ['payment_method' => null]))
        ->post(frontRoute('checkout.payment.save'), ['payment_method' => 'cod'])
        ->assertSessionHasErrors('payment_method');
});

test('a prepaid order goes to the payment page', function () {
    $variant = cartVariant(['price' => '799']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $session = [config('order.checkout_session_key') => orderCheckout($customer, 'prepaid')->toArray()];
    $token = $session[config('order.checkout_session_key')]['token'];

    $response = $this->actingAs($customer, 'customer')->withSession($session)->post(frontRoute('checkout.place'), ['token' => $token]);
    $order = SalesOrder::query()->where('placed_token', $token)->firstOrFail();

    $response->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->order_no]));

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('payment.pay', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee($order->order_no);
    // Until it is paid, the success page sends the buyer back to pay.
    $this->actingAs($customer, 'customer')->get($order->confirmationUrl())->assertRedirect(frontRoute('payment.pay', ['orderNo' => $order->order_no]));
});

test('a changed total sends the buyer back to the summary', function () {
    $variant = cartVariant(['price' => '499']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $session = checkoutSessionData($customer);

    $this->actingAs($customer, 'customer')
        ->withSession($session)
        ->post(frontRoute('checkout.place'), ['token' => $session[config('order.checkout_session_key')]['token'], 'expected_total' => '450'])
        ->assertRedirect(frontRoute('checkout.summary'))
        ->assertSessionHas('error', trans('order::front.errors.price_changed'));

    expect(SalesOrder::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('the reseller block shows for resellers and saves the end customer', function () {
    $variant = cartVariant(['price' => '400', 'mrp' => '800']);
    $reseller = orderCustomer(reseller: true);
    orderCart($reseller, [$variant]);
    $address = $reseller->address()->firstOrFail();
    $pincode = orderPincode();

    $this->actingAs($reseller, 'customer')
        ->get(frontRoute('checkout.address'))
        ->assertSuccessful()
        ->assertSee('data-reseller-block', false)
        ->assertSee(trans('order::front.reseller.toggle'));

    $response = $this->actingAs($reseller, 'customer')
        ->withSession(checkoutSessionData($reseller))
        ->post(frontRoute('checkout.address.save'), [
            'address_id' => $address->id,
            'reseller' => '1',
            'end_customer' => ['name' => 'Meena', 'phone' => '9876543210', 'address_line1' => '4 Temple St', 'pincode' => $pincode->pincode],
            'prices' => [$variant->id => '520'],
        ])
        ->assertRedirect(frontRoute('checkout.payment'));

    $stored = $response->baseResponse->getSession()?->get(config('order.checkout_session_key')) ?? session(config('order.checkout_session_key'));
    expect($stored['reseller'])->toBeTrue()
        ->and($stored['end_customer']['pincode'])->toBe($pincode->pincode)
        ->and($stored['customer_prices'][$variant->id])->toBe('520.00');

    $summary = app(CheckoutService::class)->summary($reseller, CheckoutSession::fromArray($stored));
    expect($summary->resellerMargin->toDecimal())->toBe('120.00')
        ->and($summary->grandTotal()->toDecimal())->toBe('520.00');
});

test('buy now checks out only the buy-now line', function () {
    $inCart = cartVariant();
    $buyNow = cartVariant(['price' => '299']);
    $customer = orderCustomer();
    orderCart($customer, [$inCart]);
    cartService()->buyNow($customer, $buyNow->id, 1);

    $this->actingAs($customer, 'customer')->get(frontRoute('checkout.index').'?cart=buy_now')->assertRedirect(frontRoute('checkout.address'));

    $order = placeOrder($customer, orderCheckout($customer, 'cod', ['cart_type' => Cart::TYPE_BUY_NOW]))->order;

    expect($order->grand_total->toDecimal())->toBe('299.00')
        ->and(Cart::query()->where('customer_id', $customer->id)->buyNow()->exists())->toBeFalse()
        ->and(cartService()->count(customerOwner($customer)))->toBe(1);
});
