<?php

use Illuminate\Support\Facades\Event;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Models\SellerQualityMetric;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\SellerQualityService;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
    $this->seller = orderSeller();
    $this->owner = fulfilmentOwner($this->seller);
});

test('the desk shows tabs with counts and the orders of the chosen tab', function () {
    [, $pending] = pendingFulfilmentOrder($this->seller);
    $ready = readyFulfilmentOrder($this->seller);

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.order.index', ['tab' => 'pending']))
        ->assertOk()
        ->assertSee(trans('order::fulfilment.tabs.pending'))
        ->assertSee($pending->sub_order_no)
        ->assertDontSee($ready->sub_order_no)
        ->assertSee(trans('order::fulfilment.desk.bulk.accept'));

    $this->get(route('seller.order.index', ['tab' => 'ready']))
        ->assertOk()
        ->assertSee($ready->sub_order_no)
        ->assertDontSee($pending->sub_order_no)
        ->assertSee(trans('order::fulfilment.desk.bulk.labels'));

    expect(app(SellerOrderDeskRepository::class)->tabCounts($this->seller->id))
        ->toMatchArray(['pending' => 1, 'ready' => 1, 'transit' => 0, 'cancelled' => 0]);
});

test('tab counts are flushed when an order changes status', function () {
    [, $pending] = pendingFulfilmentOrder($this->seller);
    $desk = app(SellerOrderDeskRepository::class);

    expect($desk->tabCounts($this->seller->id)['pending'])->toBe(1);

    fulfilment()->accept($pending);

    expect($desk->tabCounts($this->seller->id))->toMatchArray(['pending' => 0, 'ready' => 1]);
});

test('filters narrow the list by order number, SKU, payment and sub-filter', function () {
    $labelled = labelledFulfilmentOrder($this->seller);
    $plain = readyFulfilmentOrder($this->seller);
    $sku = $labelled->items()->value('seller_sku');
    $desk = app(SellerOrderDeskRepository::class);
    $base = ['tab' => 'ready', 'sub' => null, 'q' => '', 'sku' => '', 'payment' => null, 'sla' => null, 'from' => null, 'to' => null];

    $ids = fn (array $filters): array => $desk->orders($this->seller->id, array_merge($base, $filters), 50)->getCollection()->modelKeys();

    expect($ids(['q' => $labelled->sub_order_no]))->toBe([$labelled->id])
        ->and($ids(['sku' => $sku]))->toBe([$labelled->id])
        ->and($ids(['sub' => 'label_pending']))->toBe([$plain->id])
        ->and($ids(['payment' => 'prepaid']))->toBe([])
        ->and($ids(['payment' => 'cod']))->toHaveCount(2)
        ->and($ids(['from' => now()->addDay()->toDateString()]))->toBe([]);

    $this->actingAs($this->owner, 'seller')
        ->postJson(route('seller.order.filters'), ['tab' => 'ready', 'q' => $plain->sub_order_no])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, $plain->sub_order_no) && ! str_contains($html, $labelled->sub_order_no));
});

test('the order detail opens as a page and as drawer content', function () {
    $ready = readyFulfilmentOrder($this->seller);

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.order.show', [$ready->sub_order_no]))
        ->assertOk()
        ->assertSee($ready->sub_order_no)
        ->assertSee(trans('order::fulfilment.cancel.title'));

    $this->getJson(route('seller.order.show', [$ready->sub_order_no]))
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, (string) $ready->items()->value('seller_sku')));
});

test('the seller cancels with a reason, which counts as a seller cancellation', function () {
    Event::fake([SellerPenaltyIncurred::class]);
    $ready = readyFulfilmentOrder($this->seller);

    $this->actingAs($this->owner, 'seller')
        ->post(route('seller.order.cancel', [$ready->id]), ['reason' => 'out_of_stock'])
        ->assertSessionHas('success');

    $ready->refresh();
    expect($ready->status)->toBe(SellerOrder::STATUS_CANCELLED)
        ->and($ready->cancelled_by)->toBe(OrderCancellationService::BY_SELLER)
        ->and($ready->cancel_reason)->toBe(trans('order::fulfilment.cancel_reasons.out_of_stock'));

    Event::assertDispatched(SellerPenaltyIncurred::class, fn (SellerPenaltyIncurred $event): bool => $event->kind === SellerPenaltyIncurred::KIND_SELLER_CANCEL);

    $this->post(route('seller.order.cancel', [$ready->id]), ['reason' => 'other'])->assertSessionHasErrors('note');
});

test('a shipped order cannot be cancelled by the seller', function () {
    $packed = packedFulfilmentOrder($this->seller);
    fulfilmentScans($packed, ['picked_up']);

    $this->actingAs($this->owner, 'seller')
        ->post(route('seller.order.cancel', [$packed->id]), ['reason' => 'out_of_stock'])
        ->assertSessionHas('error');

    expect($packed->fresh()->status)->toBe(SellerOrder::STATUS_SHIPPED);
});

test('the desk exports the current tab as CSV', function () {
    $ready = readyFulfilmentOrder($this->seller);

    $response = $this->actingAs($this->owner, 'seller')->get(route('seller.order.export', ['tab' => 'ready']));
    $csv = $response->streamedContent();

    $response->assertOk();
    expect($csv)->toContain(trans('order::fulfilment.export.sub_order_no'))
        ->toContain($ready->sub_order_no)
        ->toContain((string) $ready->invoice_no);
});

test('the seller switches on auto-accept and picks the label paper', function () {
    $this->actingAs($this->owner, 'seller')->get(route('seller.settings.orders'))->assertOk();

    $this->post(route('seller.settings.orders.update'), ['auto_accept_orders' => (int) config('core.yes'), 'label_format' => 'thermal_4x6'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->seller->fresh()->auto_accept_orders)->toBe((int) config('core.yes'))
        ->and($this->seller->fresh()->label_format)->toBe('thermal_4x6');

    $ready = readyFulfilmentOrder($this->seller);
    expect(fulfilment()->generateLabels($this->seller->fresh(), [$ready])->label_format)->toBe('thermal_4x6');
});

test('the dashboard shows real order counts and the quality card', function () {
    pendingFulfilmentOrder($this->seller);
    readyFulfilmentOrder($this->seller);

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertViewHas('orderKpis', fn (array $kpis): bool => $kpis['pending_acceptance'] === 1 && $kpis['ready_to_ship'] === 1 && $kpis['orders_today'] === 2)
        ->assertSee(trans('order::fulfilment.quality.title'));
});

test('the nightly snapshot counts orders, seller cancellations and late dispatches', function () {
    $cancelled = readyFulfilmentOrder($this->seller);
    fulfilment()->sellerCancel($cancelled, 'price_error');
    $late = packedFulfilmentOrder($this->seller);
    SellerOrder::query()->whereKey($late->id)->update(['sla_dispatch_by' => now()->subHour()]);
    fulfilmentScans($late->fresh(), ['picked_up']);

    $this->artisan('order:quality-snapshot', ['--date' => now()->toDateString()])->assertSuccessful();

    $metric = SellerQualityMetric::query()->forSeller($this->seller->id)->whereDate('date', now()->toDateString())->firstOrFail();
    expect($metric->orders)->toBe(2)
        ->and($metric->cancelled_by_seller)->toBe(1)
        ->and($metric->late_dispatch)->toBe(1);

    // Re-running the day replaces the row.
    app(SellerQualityService::class)->snapshot(now());
    expect(SellerQualityMetric::query()->forSeller($this->seller->id)->count())->toBe(1);

    $summary = app(SellerOrderDeskRepository::class)->qualitySummary($this->seller->id);
    expect($summary['orders'])->toBe(2)
        ->and($summary['cancellation_rate'])->toBe(50.0);
});
