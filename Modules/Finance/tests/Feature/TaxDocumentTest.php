<?php

use Modules\Core\Services\SequenceService;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Services\TaxDocumentService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    $this->documents = app(TaxDocumentService::class);
    $this->fy = app(SequenceService::class)->financialYear();
});

test('accepting an order issues its tax invoice with the seller\'s number series and the item totals', function () {
    $seller = financeSeller();
    $sellerOrder = readyFulfilmentOrder($seller, ['price' => '1050', 'stock' => 5]);
    $invoice = TaxDocument::query()->where('seller_order_id', $sellerOrder->id)->where('type', 'tax_invoice')->firstOrFail();
    $item = $sellerOrder->items()->firstOrFail();

    expect($invoice->number)->toBe($sellerOrder->invoice_no)
        ->and($invoice->number)->toStartWith(strtoupper($seller->code).'/'.$this->fy.'/')
        ->and($invoice->taxable_value->toDecimal())->toBe($item->taxable_value->toDecimal())
        ->and($invoice->total->toDecimal())->toBe($item->taxable_value->plus($item->taxAmount())->toDecimal())
        // Rendered lazily on the synchronous queue.
        ->and($invoice->path)->toBeNull();

    $path = $this->documents->pathFor($invoice);

    expect(substr($this->documents->disk()->get($path), 0, 4))->toBe('%PDF')
        ->and($invoice->refresh()->generated_at)->not->toBeNull();
});

test('a refunded return gets a credit note referencing the invoice, numbered per seller per year', function () {
    $seller = financeSeller();
    [, $first, , $customer] = financeDeliveredOrder($seller, '1050', 2, '5');
    [, $second] = financeDeliveredOrder($seller, '525', 1, '5', customer: $customer);
    returnsSettings(['auto_approve_max_return_rate' => 100]);

    financeRefundedReturn($first, $customer);
    financeRefundedReturn($second, $customer);

    $notes = TaxDocument::query()->where('seller_id', $seller->id)->where('type', 'credit_note')->orderBy('id')->get();
    $prefix = 'CN/'.strtoupper($seller->code).'/'.$this->fy.'/';

    expect($notes)->toHaveCount(2)
        ->and($notes[0]->number)->toBe($prefix.'000001')
        ->and($notes[1]->number)->toBe($prefix.'000002')
        ->and($notes[0]->reference_number)->toBe($first->refresh()->invoice_no)
        // One of two units returned: half of the line's taxable value (1000 + 1000).
        ->and($notes[0]->taxable_value->toDecimal())->toBe('1000.00')
        ->and($notes[0]->total->toDecimal())->toBe('1050.00')
        ->and($notes[0]->lines[0]['qty'])->toBe(1);

    $path = $this->documents->pathFor($notes[0]);
    expect(substr($this->documents->disk()->get($path), 0, 4))->toBe('%PDF');
});

test('no fee invoice is issued in the zero-commission model', function () {
    financeDeliveredOrder();

    expect($this->documents->issueFeeInvoices(now()->format('Y-m')))->toBe([])
        ->and(TaxDocument::query()->where('type', 'fee_invoice')->exists())->toBeFalse();
});

test('the monthly fee invoice bills commission net of reversals with its GST', function () {
    $seller = financeSeller();
    CommissionRule::factory()->forSeller($seller)->percent('10')->create();
    [, $sellerOrder, , $customer] = financeDeliveredOrder($seller, '1050', 2, '5');
    financeRefundedReturn($sellerOrder, $customer);

    $invoices = $this->documents->issueFeeInvoices(now()->format('Y-m'));
    $again = $this->documents->issueFeeInvoices(now()->format('Y-m'));

    // Commission 210 − 105 reversed = 105; GST 37.80 − 18.90 = 18.90.
    expect($invoices)->toHaveCount(1)
        ->and($again[0]->id)->toBe($invoices[0]->id)
        ->and($invoices[0]->number)->toBe('FEE/'.$this->fy.'/000001')
        ->and($invoices[0]->taxable_value->toDecimal())->toBe('105.00')
        ->and($invoices[0]->taxTotal()->toDecimal())->toBe('18.90')
        ->and($invoices[0]->total->toDecimal())->toBe('123.90')
        ->and($invoices[0]->period)->toBe(now()->format('Y-m'));

    expect(substr($this->documents->disk()->get($this->documents->pathFor($invoices[0])), 0, 4))->toBe('%PDF');
});
