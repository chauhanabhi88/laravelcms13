<?php

use Illuminate\Http\UploadedFile;
use Modules\Finance\Models\CodRemittance;
use Modules\Finance\Models\CodRemittanceLine;
use Modules\Finance\Services\CodRemittanceService;
use Modules\Logistics\Models\Shipment;
use Modules\Payment\Models\Payment;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    $this->remittances = app(CodRemittanceService::class);
    [$this->order, $this->sellerOrder] = financeDeliveredOrder();
    $this->shipment = Shipment::query()->where('seller_order_id', $this->sellerOrder->id)->where('direction', 'forward')->firstOrFail();
});

test('a matching AWB reconciles the remittance and marks the COD payment remitted', function () {
    expect(Payment::query()->where('sales_order_id', $this->order->id)->value('status'))->toBe(Payment::STATUS_COLLECTED);

    [$remittance] = $this->remittances->import([remittanceRow('CRF1001', $this->shipment->awb, $this->shipment->cod_amount->toDecimal())]);

    expect($remittance->status)->toBe(CodRemittance::STATUS_RECONCILED)
        ->and($remittance->lines()->first()->isMatched())->toBeTrue()
        ->and(Payment::query()->where('sales_order_id', $this->order->id)->value('status'))->toBe(Payment::STATUS_REMITTED)
        // Importing the same remittance again does nothing.
        ->and($this->remittances->import([remittanceRow('CRF1001', $this->shipment->awb, '1.00')]))->toBe([]);
});

test('amount differences, unknown AWBs and repeats are flagged as mismatches', function () {
    [$remittance] = $this->remittances->import([
        remittanceRow('CRF2001', $this->shipment->awb, '1.00'),
        remittanceRow('CRF2001', 'NOSUCHAWB123', '100.00'),
    ]);

    $issues = $remittance->lines()->pluck('issue', 'awb')->all();

    expect($remittance->status)->toBe(CodRemittance::STATUS_MISMATCH)
        ->and($remittance->mismatch_count)->toBe(2)
        ->and($issues[$this->shipment->awb])->toBe(CodRemittanceLine::ISSUE_AMOUNT_MISMATCH)
        ->and($issues['NOSUCHAWB123'])->toBe(CodRemittanceLine::ISSUE_UNKNOWN_AWB)
        ->and(Payment::query()->where('sales_order_id', $this->order->id)->value('status'))->toBe(Payment::STATUS_COLLECTED);

    [$again] = $this->remittances->import([remittanceRow('CRF2002', $this->shipment->awb, $this->shipment->cod_amount->toDecimal())]);
    [$duplicate] = $this->remittances->import([remittanceRow('CRF2003', $this->shipment->awb, $this->shipment->cod_amount->toDecimal())]);

    expect($again->status)->toBe(CodRemittance::STATUS_RECONCILED)
        ->and($duplicate->lines()->value('issue'))->toBe(CodRemittanceLine::ISSUE_DUPLICATE);
});

test('delivered COD shipments not remitted in time are overdue', function () {
    $this->shipment->forceFill(['delivered_at' => now()->subDays(11)])->save();

    expect($this->remittances->overdueQuery()->pluck('id')->all())->toContain($this->shipment->id);

    $this->remittances->import([remittanceRow('CRF3001', $this->shipment->awb, $this->shipment->cod_amount->toDecimal())]);

    expect($this->remittances->overdueQuery()->pluck('id')->all())->not->toContain($this->shipment->id);
});

test('Finance uploads a remittance CSV from the console', function () {
    $csv = "remittance_ref,remittance_date,utr,awb,amount\nCRF4001,".now()->toDateString().',UTR4001,'.$this->shipment->awb.','.$this->shipment->cod_amount->toDecimal()."\n";
    $admin = financeAdmin();

    $this->actingAs($admin)
        ->post(route('admin.cod_remittance.upload'), ['file' => UploadedFile::fake()->createWithContent('remittance.csv', $csv)])
        ->assertRedirect(route('admin.cod_remittance.index'))
        ->assertSessionHas('success');

    $remittance = CodRemittance::query()->where('remittance_ref', 'CRF4001')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.cod_remittance.show', [$remittance->id]))
        ->assertOk()
        ->assertSee('data-line="'.$this->shipment->awb.'"', false);

    $this->actingAs($admin)->get(route('admin.cod_remittance.index'))->assertOk();
});
