<?php

use Modules\Core\Support\Money;
use Modules\Order\Support\Pdf\AmountInWords;
use Modules\Order\Support\Pdf\PackingSlipView;
use Modules\Order\Support\Pdf\PdfRenderer;

require_once __DIR__.'/../FulfilmentHelpers.php';

beforeEach(function () {
    fulfilmentSetup();
});

test('an intra-state invoice carries the Rule 46 fields with a CGST/SGST split', function () {
    $state = orderState('Karnataka');
    $seller = orderSeller($state);
    $variant = cartVariant(['price' => '1050', 'mrp' => '1500', 'stock' => 5], $seller, ['hsn_code' => '6204', 'gst_percent' => '5']);
    $customer = orderCustomer(orderPincode($state));
    orderCart($customer, [$variant]);
    $sellerOrder = placeOrder($customer)->order->sellerOrders()->firstOrFail();
    fulfilment()->accept($sellerOrder);
    $sellerOrder->refresh();

    $invoice = app(PackingSlipView::class)->taxInvoice($sellerOrder);
    $html = view('order::pdf.tax-invoice', ['invoice' => $invoice, 'format' => 'a4_4up'])->render();
    $item = $sellerOrder->items()->firstOrFail();

    expect($invoice['interState'])->toBeFalse()
        ->and($invoice['number'])->toBe($sellerOrder->invoice_no)
        ->and($invoice['supplier']['gstin'])->toBe($seller->gstin)
        ->and($invoice['placeOfSupply'])->toBe('Karnataka')
        ->and($item->cgst->isPositive())->toBeTrue()
        ->and($html)->toContain($seller->gstin)
        ->toContain($sellerOrder->invoice_no)
        ->toContain('6204')
        ->toContain(trans('order::fulfilment.pdf.cgst'))
        ->toContain(trans('order::fulfilment.pdf.sgst'))
        ->not->toContain('>'.trans('order::fulfilment.pdf.igst').'<')
        ->toContain('Karnataka')
        ->toContain(trans('order::fulfilment.pdf.reverse_charge'))
        ->toContain(trans('order::fulfilment.pdf.authorised_signatory'))
        ->toContain('Rupees');
});

test('an inter-state invoice charges IGST', function () {
    $seller = orderSeller(orderState('Karnataka'));
    $variant = cartVariant(['price' => '1180', 'mrp' => '1500', 'stock' => 5], $seller, ['hsn_code' => '6109', 'gst_percent' => '12']);
    $customer = orderCustomer(orderPincode(orderState('Maharashtra')));
    orderCart($customer, [$variant]);
    $sellerOrder = placeOrder($customer)->order->sellerOrders()->firstOrFail();
    fulfilment()->accept($sellerOrder);

    $invoice = app(PackingSlipView::class)->taxInvoice($sellerOrder->fresh());
    $html = view('order::pdf.tax-invoice', ['invoice' => $invoice, 'format' => 'a4_4up'])->render();

    expect($invoice['interState'])->toBeTrue()
        ->and($invoice['totals']['igst']->isPositive())->toBeTrue()
        ->and($invoice['totals']['cgst']->isZero())->toBeTrue()
        ->and($invoice['placeOfSupply'])->toBe('Maharashtra')
        ->and($html)->toContain(trans('order::fulfilment.pdf.igst'))
        ->toContain('12%')
        ->toContain('6109');
});

test('the renderer produces a PDF on A4 and on 4 x 6 thermal paper', function () {
    $sellerOrder = readyFulfilmentOrder();
    $invoice = app(PackingSlipView::class)->taxInvoice($sellerOrder);

    expect(app(PdfRenderer::class)->render('order::pdf.tax-invoice', ['invoice' => $invoice]))->toStartWith('%PDF')
        ->and(app(PdfRenderer::class)->render('order::pdf.tax-invoice', ['invoice' => $invoice], 'thermal_4x6'))->toStartWith('%PDF');
});

test('amounts are written in words in the Indian numbering system', function (string $amount, string $words) {
    expect(AmountInWords::rupees(Money::of($amount)))->toBe($words);
})->with([
    ['0', 'Rupees Zero Only'],
    ['15', 'Rupees Fifteen Only'],
    ['1050.50', 'Rupees One Thousand Fifty and Fifty Paise Only'],
    ['123456', 'Rupees One Lakh Twenty Three Thousand Four Hundred Fifty Six Only'],
    ['20000000.05', 'Rupees Two Crore and Five Paise Only'],
]);
