<?php

use Modules\Customer\Contracts\AccountDeletionGuard;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;

require_once __DIR__.'/../Helpers.php';

test('a customer cannot see or cancel another customer\'s order', function () {
    $owner = Customer::factory()->create();
    $order = SalesOrder::factory()->forCustomer($owner)->withSellerOrders(1)->create();
    $other = Customer::factory()->create();

    $this->actingAs($other, 'customer')->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))->assertNotFound();
    $this->actingAs($other, 'customer')->get(frontRoute('checkout.success', ['orderNo' => $order->order_no]))->assertNotFound();
    $this->actingAs($other, 'customer')->post(frontRoute('customer.orders.cancel', ['orderNo' => $order->order_no]), ['reason' => 'other'])->assertNotFound();
    $this->actingAs($other, 'customer')->get(frontRoute('payment.pay', ['orderNo' => $order->order_no]))->assertNotFound();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED);
});

test('guests are sent to log in', function () {
    $order = SalesOrder::factory()->withSellerOrders(1)->create();

    $this->get(frontRoute('customer.orders.index'))->assertRedirect();
    $this->get(frontRoute('checkout.index'))->assertRedirect();
    $this->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))->assertRedirect();
});

test('the owner sees their orders and the detail', function () {
    $customer = Customer::factory()->create();
    $order = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(2)->create();
    SalesOrder::factory()->withSellerOrders(1)->create(['order_no' => 'ORD-2610-777777']);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.index'))
        ->assertSuccessful()
        ->assertSee($order->order_no)
        ->assertDontSee('ORD-2610-777777');

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee($order->order_no.'-1')
        ->assertSee('Cancel items')
        ->assertSee('Asha Rao');
});

test('an order still on its way holds an account deletion', function () {
    $customer = Customer::factory()->create();
    SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create();

    expect(app(AccountDeletionGuard::class)->blockingReason($customer))->toBe(trans('order::front.account.deletion_blocked'))
        ->and(app(AccountDeletionGuard::class)->blockingReason(Customer::factory()->create()))->toBeNull();
});
