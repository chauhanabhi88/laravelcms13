<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Models\ProductVariant;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
    orderSettings(['sla_grace_hours' => 12]);
});

/**
 * Pretend the order was placed $hoursAgo hours ago with a $slaHours dispatch window.
 */
function slaWindow(SellerOrder $sellerOrder, int $hoursAgo, int $slaHours): void
{
    SellerOrder::query()->whereKey($sellerOrder->id)->update([
        'created_at' => now()->subHours($hoursAgo),
        'sla_dispatch_by' => now()->subHours($hoursAgo)->addHours($slaHours),
    ]);
}

function slaWarnings(): int
{
    return DB::table('notification_log')->where('event_key', 'seller.sla.warning')->count();
}

test('the SLA warning goes out once, after 75% of the dispatch window', function () {
    $seller = orderSeller();
    fulfilmentOwner($seller);
    [, $early] = pendingFulfilmentOrder($seller);
    [, $late] = pendingFulfilmentOrder($seller);
    slaWindow($early, 10, 48);   // ~21% used
    slaWindow($late, 40, 48);    // ~83% used
    $before = slaWarnings();

    $this->artisan('order:sla-warn')->assertSuccessful();

    expect($late->fresh()->sla_warned_at)->not->toBeNull()
        ->and($early->fresh()->sla_warned_at)->toBeNull()
        ->and(slaWarnings())->toBeGreaterThan($before);

    $afterFirst = slaWarnings();
    expect(fulfilment()->warnSlaBreaches())->toBe(0)
        ->and(slaWarnings())->toBe($afterFirst);
});

test('packed orders are not warned', function () {
    $packed = packedFulfilmentOrder();
    slaWindow($packed, 40, 48);

    expect(fulfilment()->warnSlaBreaches())->toBe(0)
        ->and($packed->fresh()->sla_warned_at)->toBeNull();
});

test('orders past the deadline and the grace period are cancelled, releasing stock', function () {
    Event::fake([SellerPenaltyIncurred::class]);
    [, $breached] = pendingFulfilmentOrder(variant: ['stock' => 5]);
    [, $inGrace] = pendingFulfilmentOrder();
    slaWindow($breached, 80, 48);   // 32 h past the deadline
    slaWindow($inGrace, 54, 48);    // 6 h past, still in grace
    $variantId = $breached->items()->value('variant_id');

    $this->artisan('order:sla-cancel')->assertSuccessful();

    $breached->refresh();
    expect($breached->status)->toBe(SellerOrder::STATUS_CANCELLED)
        ->and($breached->cancelled_by)->toBe(OrderCancellationService::BY_SYSTEM)
        ->and($breached->cancel_reason)->toBe(trans('order::fulfilment.sla.cancel_reason'))
        ->and(ProductVariant::query()->findOrFail($variantId)->reserved)->toBe(0)
        ->and($inGrace->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);

    Event::assertDispatched(SellerPenaltyIncurred::class, fn (SellerPenaltyIncurred $event): bool => $event->sellerOrder->id === $breached->id && $event->kind === SellerPenaltyIncurred::KIND_SLA_BREACH);
});

test('an SLA cancellation refunds a prepaid order', function () {
    $variant = cartVariant(['price' => '700', 'stock' => 5]);
    $customer = orderCustomer();
    orderCart($customer, [$variant]);
    $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_PREPAID));
    $fake = app(FakeGateway::class);
    app(PaymentService::class)->markCaptured($result->payment, $fake->fetch($fake->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
    $sellerOrder = $result->order->sellerOrders()->firstOrFail();
    slaWindow($sellerOrder, 80, 48);

    expect(fulfilment()->cancelSlaBreached())->toBe(1)
        ->and($sellerOrder->fresh()->status)->toBe(SellerOrder::STATUS_CANCELLED)
        ->and(PaymentRefund::query()->where('payment_id', $result->payment->id)->firstOrFail()->amount->toDecimal())->toBe('700.00');
});
