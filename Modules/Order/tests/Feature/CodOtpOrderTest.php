<?php

use Modules\Order\DTO\PlaceOrderResult;
use Modules\Order\Jobs\CancelAbandonedOrders;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
    paymentSettings(['cod_otp_threshold' => '1000', 'cod_otp_window_minutes' => 15]);
    $this->sms = paymentFakeSms();
});

test('a COD order above the OTP threshold waits for the code with its seller orders held', function () {
    $variant = cartVariant(['price' => '1500', 'mrp' => '2000']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);

    $result = placeOrder($customer);

    expect($result->nextAction())->toBe(PlaceOrderResult::NEXT_COD_OTP)
        ->and($result->nextUrl())->toBe(frontRoute('payment.cod_otp.show', ['orderNo' => $result->order->order_no]))
        ->and($result->order->fresh()->status)->toBe(SalesOrder::STATUS_PENDING_COD_VERIFICATION)
        ->and(SellerOrder::query()->where('sales_order_id', $result->order->id)->value('status'))->toBe(SellerOrder::STATUS_PENDING_PAYMENT);
    $this->sms->assertSentTo($customer->phone);
});

test('the verified code confirms the order', function () {
    $variant = cartVariant(['price' => '1500', 'mrp' => '2000']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    $code = $this->sms->lastOtpFor($customer->phone);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('payment.cod_otp.verify', ['orderNo' => $order->order_no]), ['code' => str_split($code)])
        ->assertRedirect($order->confirmationUrl());

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED)
        ->and(SellerOrder::query()->where('sales_order_id', $order->id)->value('status'))->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);
});

test('an unverified COD order is cancelled after the window and its stock released', function () {
    $variant = cartVariant(['price' => '1500', 'mrp' => '2000', 'stock' => 4]);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    Payment::query()->forOrder($order->id)->update(['expires_at' => now()->subMinute()]);

    app(PaymentService::class)->expireStale();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(orderVariant($variant)->reserved)->toBe(0);
});

test('the abandoned-order sweep cancels an order whose payment already closed', function () {
    $variant = cartVariant(['price' => '1500', 'mrp' => '2000']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    // The PaymentFailed event was missed: the payment is closed, the order still waits.
    Payment::query()->forOrder($order->id)->update(['status' => Payment::STATUS_CANCELLED]);
    SalesOrder::query()->whereKey($order->id)->update(['placed_at' => now()->subHour()]);

    $result = app()->call([new CancelAbandonedOrders, 'handle']);

    expect($result['cancelled'])->toBe(1)
        ->and($order->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED)
        ->and(orderVariant($variant)->reserved)->toBe(0);
});

test('the sweep confirms an order whose payment was verified but the event missed', function () {
    $variant = cartVariant(['price' => '1500', 'mrp' => '2000']);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $order = placeOrder($customer)->order;
    Payment::query()->forOrder($order->id)->update(['cod_otp_verified_at' => now()]);
    SalesOrder::query()->whereKey($order->id)->update(['placed_at' => now()->subHour()]);

    $this->artisan('order:cancel-abandoned')->assertSuccessful();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED);
});
