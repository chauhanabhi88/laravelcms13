<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CodRiskService;
use Modules\Order\Services\OrderStatusService;
use Modules\Payment\DTO\CodEligibility;
use Modules\Payment\Services\CodEligibilityService;
use Modules\User\Models\User;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    orderSetup();
});

function shippedSellerOrder(Customer $customer): SellerOrder
{
    $sellerOrder = SalesOrder::factory()->forCustomer($customer)->withSellerOrders(1)->create()->sellerOrders()->firstOrFail();
    $statuses = app(OrderStatusService::class);

    foreach ([SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED, SellerOrder::STATUS_SHIPPED] as $status) {
        $statuses->transition($sellerOrder->fresh(), $status);
    }

    return $sellerOrder->fresh();
}

test('the RTO threshold auto-blocks COD and an admin can lift it', function () {
    $customer = Customer::factory()->withPhone()->create();
    $statuses = app(OrderStatusService::class);

    foreach (range(1, 3) as $count) {
        $statuses->transition(shippedSellerOrder($customer), SellerOrder::STATUS_RTO_INITIATED);
        expect($customer->fresh()->isCodBlocked())->toBe($count === 3);
    }

    expect($customer->fresh()->rto_count)->toBe(3);

    $this->actingAs(User::query()->forceCreate([
        'name' => 'Ops', 'email' => uniqid('ops-').'@example.test', 'password' => bcrypt('Secret#123'),
        'role_id' => DB::table('role')->insertGetId(['name' => 'Ops', 'slug' => 'ops_'.uniqid(), 'permissions' => json_encode(['admin.customer.toggle_cod_block']), 'created_at' => now(), 'updated_at' => now()]),
        'status' => 1,
    ]))->post(route('admin.customer.toggle_cod_block', updateUrlParams([$customer->id])));

    expect($customer->fresh()->isCodBlocked())->toBeFalse()
        // Only a new RTO blocks again.
        ->and(app(CodRiskService::class)->blockReason($customer->fresh(), Money::of('500'), null))->toBeNull();
});

test('a customer with too many undelivered COD orders is offered prepaid only', function () {
    orderSettings(['max_open_cod_orders' => 2]);
    $pincode = orderPincode();
    $customer = orderCustomer($pincode);
    SalesOrder::factory()->forCustomer($customer)->count(2)->create();

    $eligibility = app(CodEligibilityService::class)->check(Money::of('500'), $customer, $pincode->pincode, true);

    expect($eligibility->allowed)->toBeFalse()
        ->and($eligibility->reasonCode)->toBe(CodEligibility::REASON_RISK)
        ->and($eligibility->reason)->toBe(trans('order::front.cod_risk.open_orders', ['count' => 2]));
});

test('delivered and cancelled COD orders do not count as open', function () {
    orderSettings(['max_open_cod_orders' => 1]);
    $customer = Customer::factory()->create();
    SalesOrder::factory()->forCustomer($customer)->status(SalesOrder::STATUS_DELIVERED)->create();
    SalesOrder::factory()->forCustomer($customer)->status(SalesOrder::STATUS_CANCELLED)->create();
    SalesOrder::factory()->forCustomer($customer)->prepaid()->create();

    expect(app(CodRiskService::class)->openCodOrders($customer))->toBe(0);
});
