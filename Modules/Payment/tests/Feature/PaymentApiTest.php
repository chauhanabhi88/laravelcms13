<?php

use Laravel\Passport\Passport;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    paymentOrders();
});

test('the app gets the gateway order and key for the native SDK', function () {
    $order = paymentOrder(['amount' => Money::of('649')]);
    Passport::actingAs($order->customer, [CustomerAuthService::API_SCOPE], 'api');

    $response = $this->postJson(route('payment_api.initiate', ['orderNo' => $order->orderNo]))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment.status', Payment::STATUS_CREATED)
        ->assertJsonPath('data.payment.amount', '649.00')
        ->assertJsonPath('data.checkout.amount', 64900)
        ->assertJsonPath('data.checkout.key', 'rzp_test_fake');

    $gatewayOrderId = $response->json('data.checkout.order_id');
    expect($gatewayOrderId)->toStartWith('order_fake_');

    // The SDK's signed result.
    $this->postJson(route('payment_api.verify', ['orderNo' => $order->orderNo]), fakeGateway()->simulate($gatewayOrderId))
        ->assertSuccessful()
        ->assertJsonPath('data.payment.status', Payment::STATUS_CAPTURED);
});

test('a bad SDK signature is rejected', function () {
    $order = paymentOrder();
    $payment = app(PaymentService::class)->initiate($order, 'prepaid');
    Passport::actingAs($order->customer, [CustomerAuthService::API_SCOPE], 'api');
    $payload = fakeGateway()->simulate($payment->gateway_order_id);
    $payload['razorpay_signature'] = str_repeat('0', 64);

    $this->postJson(route('payment_api.verify', ['orderNo' => $order->orderNo]), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason', 'invalid_signature');
});

test('another customer\'s order is not found and guests are unauthorised', function () {
    $order = paymentOrder();

    $this->postJson(route('payment_api.initiate', ['orderNo' => $order->orderNo]))->assertUnauthorized();

    Passport::actingAs(Customer::factory()->create(), [CustomerAuthService::API_SCOPE], 'api');
    $this->postJson(route('payment_api.initiate', ['orderNo' => $order->orderNo]))->assertNotFound();
});

test('the app verifies the COD OTP', function () {
    paymentSettings(['cod_otp_threshold' => '1000']);
    $sms = paymentFakeSms();
    $order = paymentOrder(['amount' => Money::of('1500')]);
    app(PaymentService::class)->initiate($order, Payment::METHOD_COD);
    Passport::actingAs($order->customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('payment_api.cod_otp.verify', ['orderNo' => $order->orderNo]), ['code' => '12'])
        ->assertUnprocessable();

    $this->postJson(route('payment_api.cod_otp.verify', ['orderNo' => $order->orderNo]), ['code' => $sms->lastOtpFor($order->customer->phone)])
        ->assertSuccessful()
        ->assertJsonPath('data.payment.cod_otp_required', false);
});
