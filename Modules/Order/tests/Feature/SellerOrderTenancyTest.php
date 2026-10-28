<?php

use Modules\Order\Models\SellerOrder;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
    $this->sellerA = orderSeller();
    $this->sellerB = orderSeller();
    $this->ownerB = fulfilmentOwner($this->sellerB);
    [, $this->orderA] = pendingFulfilmentOrder($this->sellerA);
});

test('seller B does not see seller A\'s orders on the desk', function () {
    $this->actingAs($this->ownerB, 'seller')
        ->get(route('seller.order.index', ['tab' => 'pending']))
        ->assertOk()
        ->assertDontSee($this->orderA->sub_order_no);
});

test('seller B cannot open seller A\'s order', function () {
    $this->actingAs($this->ownerB, 'seller')
        ->get(route('seller.order.show', [$this->orderA->sub_order_no]))
        ->assertNotFound();
});

test('seller B cannot accept or pack seller A\'s orders', function () {
    $this->actingAs($this->ownerB, 'seller')
        ->post(route('seller.order.accept'), ['ids' => [$this->orderA->id]])
        ->assertSessionHas('error', trans('order::fulfilment.errors.nothing_selected'));

    $this->post(route('seller.order.pack'), ['ids' => [$this->orderA->id]])
        ->assertSessionHas('error', trans('order::fulfilment.errors.nothing_selected'));

    $this->post(route('seller.label.generate'), ['ids' => [$this->orderA->id]])
        ->assertSessionHas('error', trans('order::fulfilment.errors.nothing_selected'));

    expect($this->orderA->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE)
        ->and($this->orderA->fresh()->accepted_at)->toBeNull();
});

test('seller B cannot cancel seller A\'s order or confirm its receipt', function () {
    $this->actingAs($this->ownerB, 'seller')
        ->post(route('seller.order.cancel', [$this->orderA->id]), ['reason' => 'out_of_stock'])
        ->assertNotFound();

    $this->post(route('seller.order.return_receipt', [$this->orderA->id]), ['condition' => 'good'])
        ->assertNotFound();

    expect($this->orderA->fresh()->status)->toBe(SellerOrder::STATUS_PENDING_ACCEPTANCE);
});

test('seller B cannot download seller A\'s documents', function () {
    $ready = labelledFulfilmentOrder($this->sellerA);
    $document = $ready->documents()->where('type', 'tax_invoice')->firstOrFail();

    $this->actingAs($this->ownerB, 'seller')
        ->get(route('seller.order.document', [$document->id]))
        ->assertNotFound();

    $this->actingAs(fulfilmentOwner($this->sellerA), 'seller')
        ->get(route('seller.order.document', [$document->id]))
        ->assertSuccessful();
});

test('catalog-only staff cannot reach the order desk', function () {
    $catalogUser = SellerUser::factory()->for($this->sellerA)->role('catalog')->create();

    $this->actingAs($catalogUser, 'seller')->get(route('seller.order.index'))->assertForbidden();
});
