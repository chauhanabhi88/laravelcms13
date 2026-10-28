<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Events\ReturnReceiptConfirmed;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\ProductVariant;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
});

function rtoVariantStock(SellerOrder $sellerOrder): int
{
    return ProductVariant::query()->findOrFail($sellerOrder->items()->value('variant_id'))->stock;
}

test('an RTO received in good condition is restocked', function () {
    Event::fake([ReturnReceiptConfirmed::class]);
    $sellerOrder = rtoDeliveredOrder();
    $stockBefore = rtoVariantStock($sellerOrder);

    expect($sellerOrder->status)->toBe(SellerOrder::STATUS_RTO_DELIVERED)
        ->and($sellerOrder->awaitsReceipt())->toBeTrue();

    fulfilment()->confirmReturnReceipt($sellerOrder, 'good');

    $sellerOrder->refresh();
    expect($sellerOrder->receipt_condition)->toBe('good')
        ->and($sellerOrder->receipt_confirmed_by)->toBe('seller')
        ->and($sellerOrder->awaitsReceipt())->toBeFalse()
        ->and(rtoVariantStock($sellerOrder))->toBe($stockBefore + 1)
        ->and(Shipment::query()->where('seller_order_id', $sellerOrder->id)->value('rto_restocked'))->toBe((int) config('core.yes'));

    Event::assertDispatched(ReturnReceiptConfirmed::class, fn (ReturnReceiptConfirmed $event): bool => $event->restocked && ! $event->raisesClaim());

    expect(fn () => fulfilment()->confirmReturnReceipt($sellerOrder, 'good'))->toThrow(OrderException::class);
});

test('a damaged RTO raises a claim with photos and is not restocked', function () {
    Event::fake([ReturnReceiptConfirmed::class]);
    $seller = orderSeller();
    $sellerOrder = rtoDeliveredOrder($seller);
    $stockBefore = rtoVariantStock($sellerOrder);

    $this->actingAs(fulfilmentOwner($seller), 'seller')
        ->from(route('seller.order.index', ['tab' => 'returns']))
        ->post(route('seller.order.return_receipt', [$sellerOrder->id]), [
            'condition' => 'damaged',
            'note' => 'Box crushed, kurti torn',
            'photos' => [UploadedFile::fake()->image('damage.jpg', 800, 800)],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $sellerOrder->refresh();
    expect($sellerOrder->receipt_condition)->toBe('damaged')
        ->and($sellerOrder->receipt_note)->toBe('Box crushed, kurti torn')
        ->and($sellerOrder->receipt_photos)->toHaveCount(1)
        ->and(Storage::disk(config('order.fulfilment.documents_disk'))->exists($sellerOrder->receipt_photos[0]))->toBeTrue()
        ->and(rtoVariantStock($sellerOrder))->toBe($stockBefore);

    Event::assertDispatched(ReturnReceiptConfirmed::class, fn (ReturnReceiptConfirmed $event): bool => $event->raisesClaim() && $event->photos === $sellerOrder->receipt_photos);
});

test('a damaged receipt needs a note and photos', function () {
    $seller = orderSeller();
    $sellerOrder = rtoDeliveredOrder($seller);

    $this->actingAs(fulfilmentOwner($seller), 'seller')
        ->post(route('seller.order.return_receipt', [$sellerOrder->id]), ['condition' => 'wrong_item'])
        ->assertSessionHasErrors(['note', 'photos']);

    expect($sellerOrder->fresh()->receipt_confirmed_at)->toBeNull();
});

test('unconfirmed RTOs are confirmed as good after the configured days', function () {
    orderSettings(['auto_confirm_return_days' => 7]);
    $old = rtoDeliveredOrder();
    $recent = rtoDeliveredOrder();
    $old->statusHistories()->where('to_status', SellerOrder::STATUS_RTO_DELIVERED)->update(['created_at' => now()->subDays(8)]);

    $this->artisan('order:auto-confirm-receipts')->assertSuccessful();

    expect($old->fresh()->receipt_condition)->toBe('good')
        ->and($old->fresh()->receipt_confirmed_by)->toBe('system')
        ->and($recent->fresh()->receipt_confirmed_at)->toBeNull();
});

test('orders not back with the seller cannot be confirmed', function () {
    $packed = packedFulfilmentOrder();

    expect(fn () => fulfilment()->confirmReturnReceipt($packed, 'good'))->toThrow(OrderException::class);
});
