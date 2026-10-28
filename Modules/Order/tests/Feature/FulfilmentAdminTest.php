<?php

use Modules\Logistics\Models\PickupBatch;
use Modules\Order\Models\SalesOrderNote;
use Modules\Order\Models\SellerOrder;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
});

test('Ops force a seller order status with a reason, recorded in the history and notes', function () {
    $ready = readyFulfilmentOrder();
    $admin = orderAdmin();

    $this->actingAs($admin)
        ->get(route('admin.seller_order.show', [$ready->id]))
        ->assertOk()
        ->assertSee(trans('order::seller_order.labels.force_status'));

    $this->from(route('admin.seller_order.show', [$ready->id]))
        ->post(route('admin.seller_order.force_status', [$ready->id]), ['status' => SellerOrder::STATUS_PACKED, 'reason' => 'Seller packed it, label printed offline'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $ready->refresh();
    $history = $ready->statusHistories()->reorder('id', 'desc')->firstOrFail();

    expect($ready->status)->toBe(SellerOrder::STATUS_PACKED)
        ->and($history->reason)->toBe('Seller packed it, label printed offline')
        ->and($history->actor_id)->toBe($admin->id)
        ->and($history->meta['forced'] ?? null)->toBeTrue()
        ->and(SalesOrderNote::query()->where('sales_order_id', $ready->sales_order_id)->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('a forced status must follow the state machine and needs a reason', function () {
    [, $pending] = pendingFulfilmentOrder();
    $admin = orderAdmin();

    $this->actingAs($admin)
        ->post(route('admin.seller_order.force_status', [$pending->id]), ['status' => SellerOrder::STATUS_DELIVERED, 'reason' => 'Customer says delivered'])
        ->assertSessionHas('error');

    $this->post(route('admin.seller_order.force_status', [$pending->id]), ['status' => SellerOrder::STATUS_READY_TO_SHIP])
        ->assertSessionHasErrors('reason');

    $this->post(route('admin.seller_order.force_status', [$pending->id]), ['status' => SellerOrder::STATUS_CANCELLED, 'reason' => 'Cancel it please'])
        ->assertSessionHasErrors('status');

    expect($pending->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);
});

test('forcing a status needs its own permission', function () {
    [, $pending] = pendingFulfilmentOrder();
    $viewer = orderAdmin(['admin.seller_order.index', 'admin.seller_order.show']);

    $this->actingAs($viewer)
        ->get(route('admin.seller_order.show', [$pending->id]))
        ->assertOk()
        ->assertDontSee(trans('order::seller_order.labels.force_status_submit'));

    $this->post(route('admin.seller_order.force_status', [$pending->id]), ['status' => SellerOrder::STATUS_READY_TO_SHIP, 'reason' => 'Trying anyway'])
        ->assertForbidden();
});

test('Logistics Ops see pickup batches with their packages and print the manifest', function () {
    $seller = orderSeller();
    $packed = packedFulfilmentOrder($seller);
    $outcome = fulfilment()->createManifest([$packed], now()->addDay());
    $admin = logisticsAdmin();

    $this->actingAs($admin)->get(route('admin.pickup_batch.index'))->assertOk();
    $this->postJson(route('admin.pickup_batch.filters'), ['search' => $outcome->manifestNo])
        ->assertOk()
        ->assertJsonPath('content.html', fn (string $html): bool => str_contains($html, $outcome->manifestNo));

    $this->get(route('admin.pickup_batch.show', [$outcome->pickupBatchId]))
        ->assertOk()
        ->assertSee($outcome->manifestNo)
        ->assertSee($packed->sub_order_no);

    $response = $this->get(route('admin.pickup_batch.download', [$outcome->pickupBatchId]))->assertOk();
    expect($response->getContent())->toStartWith('%PDF')
        ->and(PickupBatch::query()->count())->toBe(1);
});
