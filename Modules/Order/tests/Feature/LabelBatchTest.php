<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Storage;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Models\SalesOrderDocument;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Support\Pdf\PackingSlipView;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    $this->courier = fulfilmentSetup();
    $this->disk = Storage::disk(config('order.fulfilment.documents_disk'));
});

test('a label batch bundles the courier label with our packing slips and invoices', function () {
    $seller = orderSeller();
    $first = readyFulfilmentOrder($seller);
    $second = readyFulfilmentOrder($seller);

    $batch = fulfilment()->generateLabels($seller, [$first, $second])->refresh();

    expect($batch->status)->toBe(LabelBatch::STATUS_READY)
        ->and($batch->orders_count)->toBe(2)
        ->and($batch->file_name)->toEndWith('.zip')
        ->and($batch->provider_labels)->toHaveCount(1)
        ->and(fakeCourier()->called('generateLabel'))->toBe(1)
        ->and($this->disk->exists($batch->path))->toBeTrue();

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($tmp, $this->disk->get($batch->path));
    $zip->open($tmp);
    $names = array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));

    expect($names)->toContain('packing-slips-and-invoices.pdf')
        ->and(collect($names)->first(fn (string $name): bool => str_starts_with($name, 'courier-labels-1')))->not->toBeNull()
        ->and($zip->getFromName('packing-slips-and-invoices.pdf'))->toStartWith('%PDF');
    $zip->close();
    @unlink($tmp);

    foreach ([$first, $second] as $sellerOrder) {
        $sellerOrder->refresh();
        expect($sellerOrder->label_generated_at)->not->toBeNull()
            ->and($sellerOrder->documents()->pluck('type')->all())->toContain(SalesOrderDocument::TYPE_LABEL, SalesOrderDocument::TYPE_PACKING_SLIP, SalesOrderDocument::TYPE_TAX_INVOICE)
            ->and(Shipment::query()->where('seller_order_id', $sellerOrder->id)->value('status'))->toBe(Shipment::STATUS_LABEL_GENERATED);
    }

    $invoice = SalesOrderDocument::query()->where('seller_order_id', $first->id)->where('type', SalesOrderDocument::TYPE_TAX_INVOICE)->firstOrFail();
    expect($invoice->number)->toBe($first->invoice_no)
        ->and($this->disk->get($invoice->path))->toStartWith('%PDF');
});

test('a courier label that cannot be downloaded is listed as a link', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response('Not found', 404)]);
    $seller = orderSeller();
    $sellerOrder = readyFulfilmentOrder($seller);

    $batch = fulfilment()->generateLabels($seller, [$sellerOrder])->refresh();

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($tmp, $this->disk->get($batch->path));
    $zip->open($tmp);

    expect($batch->status)->toBe(LabelBatch::STATUS_READY)
        ->and($zip->getFromName('courier-labels.txt'))->toContain('https://labels.example.test/');
    $zip->close();
    @unlink($tmp);
});

test('orders that are not accepted cannot get labels', function () {
    $seller = orderSeller();
    [, $pending] = pendingFulfilmentOrder($seller);

    expect(fn () => fulfilment()->generateLabels($seller, [$pending]))->toThrow(OrderException::class);
    expect(LabelBatch::query()->count())->toBe(0);
});

test('packing requires a printed label', function () {
    $seller = orderSeller();
    $unlabelled = readyFulfilmentOrder($seller);
    $labelled = labelledFulfilmentOrder($seller);

    $result = fulfilment()->markPacked([$unlabelled, $labelled]);

    expect($result->outcome($unlabelled->id))->toBe(FulfilmentResult::FAILED)
        ->and($result->outcome($labelled->id))->toBe(FulfilmentResult::OK)
        ->and($unlabelled->fresh()->status)->toBe(SellerOrder::STATUS_READY_TO_SHIP)
        ->and($labelled->fresh()->status)->toBe(SellerOrder::STATUS_PACKED)
        ->and($labelled->fresh()->packed_at)->not->toBeNull();
});

test('the packing slip shows the AWB, the items and the supplier as sender', function () {
    $seller = orderSeller();
    $sellerOrder = readyFulfilmentOrder($seller);
    $shipment = app(FulfilmentLogistics::class)->shipments([$sellerOrder->id])[$sellerOrder->id];
    $item = $sellerOrder->items()->firstOrFail();

    $html = view('order::pdf.packing-slip', ['documents' => [['slip' => app(PackingSlipView::class)->packingSlip($sellerOrder, $shipment), 'invoice' => null]], 'format' => 'a4_4up'])->render();

    expect($html)->toContain($shipment->awb)
        ->toContain($sellerOrder->sub_order_no)
        ->toContain(e($item->name))
        ->toContain(e($seller->displayName()));
});

test('a white-labelled reseller order shows the reseller, not the supplier, as sender', function () {
    orderSettings(['reseller_white_label' => 1]);
    $seller = orderSeller();
    $seller->forceFill(['store_name' => 'Supplier Hidden Store'])->save();
    $variant = cartVariant(['price' => '400', 'stock' => 5], $seller);
    $reseller = orderCustomer(reseller: true);
    $reseller->forceFill(['reseller_business_name' => 'Asha Resells'])->save();
    orderCart($reseller, [$variant]);

    $checkout = app(CheckoutService::class);
    $snapshot = $checkout->start($reseller, 'standard');
    $session = $checkout->setReseller(orderCheckout($reseller, CheckoutSession::METHOD_COD), $reseller, $snapshot, true, [
        'name' => 'End Customer',
        'phone' => '9876543210',
        'address_line1' => '12 Lake Road',
        'address_line2' => 'Ward 4',
        'pincode' => orderPincode()->pincode,
    ], [$variant->id => '450']);
    $order = placeOrder($reseller, $session)->order;
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    $slip = app(PackingSlipView::class)->packingSlip($sellerOrder);
    $html = view('order::pdf.packing-slip', ['documents' => [['slip' => $slip, 'invoice' => null]], 'format' => 'thermal_4x6'])->render();

    expect($slip['whiteLabel'])->toBeTrue()
        ->and($slip['sender']['name'])->toBe('Asha Resells')
        ->and($html)->toContain('Asha Resells')
        ->not->toContain('Supplier Hidden Store')
        ->toContain('End Customer');
});

test('the seller downloads a ready batch and cannot download another seller\'s', function () {
    $seller = orderSeller();
    $sellerOrder = readyFulfilmentOrder($seller);
    $owner = fulfilmentOwner($seller);

    $this->actingAs($owner, 'seller')
        ->from(route('seller.order.index', ['tab' => 'ready']))
        ->post(route('seller.label.generate'), ['ids' => [$sellerOrder->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = latestLabelBatch($seller);

    $this->get(route('seller.label.status', [$batch->id]))->assertOk()->assertJsonPath('ready', true);
    $this->get(route('seller.label.download', [$batch->id]))->assertOk()->assertDownload($batch->file_name);

    $other = fulfilmentOwner(orderSeller());
    $this->actingAs($other, 'seller')->get(route('seller.label.download', [$batch->id]))->assertNotFound();
});
