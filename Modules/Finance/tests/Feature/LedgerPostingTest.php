<?php

use Modules\Core\Support\Money;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Services\ReturnClaimService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
});

test('a delivered order posts the sale, commission + GST, TCS and TDS, payable after the return window and payout delay', function () {
    $seller = financeSeller();
    CommissionRule::factory()->forSeller($seller)->percent('10')->create();

    [, $sellerOrder] = financeDeliveredOrder($seller, '1050', 1, '5');
    $entries = ledgerByType($sellerOrder);

    expect(array_keys($entries))->toEqualCanonicalizing(['sale', 'commission', 'tcs', 'tds'])
        ->and($entries['sale']->direction)->toBe('credit')
        ->and($entries['sale']->amount->toDecimal())->toBe('1050.00')
        ->and($entries['commission']->direction)->toBe('debit')
        ->and($entries['commission']->amount->toDecimal())->toBe('105.00')
        ->and($entries['commission']->tax_amount->toDecimal())->toBe('18.90')
        ->and($entries['tcs']->amount->toDecimal())->toBe('10.00')
        ->and($entries['tcs']->base_amount->toDecimal())->toBe('1000.00')
        ->and($entries['tds']->amount->toDecimal())->toBe('1.05');

    $expected = $sellerOrder->return_window_ends_at->copy()->startOfDay()->addDays(7)->toDateString();

    foreach ($entries as $entry) {
        expect($entry->status)->toBe(SellerLedgerEntry::STATUS_PENDING)
            ->and($entry->eligible_on->toDateString())->toBe($expected)
            ->and($entry->sales_order_item_id)->not->toBeNull();
    }
});

test('the default zero-commission model posts no commission entry', function () {
    [, $sellerOrder] = financeDeliveredOrder();

    expect(ledgerByType($sellerOrder))->not->toHaveKey('commission')
        ->and(SellerLedgerEntry::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(3);
});

test('replaying the delivery posts nothing new', function () {
    [, $sellerOrder] = financeDeliveredOrder();
    $before = SellerLedgerEntry::query()->count();

    expect(app(LedgerPosterImpl::class)->postDelivered(SellerOrder::query()->findOrFail($sellerOrder->id)))->toBe(0)
        ->and(SellerLedgerEntry::query()->count())->toBe($before);
});

test('a refunded return reverses the sale, commission, TCS and TDS for the returned units', function () {
    $seller = financeSeller();
    CommissionRule::factory()->forSeller($seller)->percent('10')->create();
    [, $sellerOrder, , $customer] = financeDeliveredOrder($seller, '1050', 2, '5');

    $return = financeRefundedReturn($sellerOrder, $customer);

    $reversals = SellerLedgerEntry::query()->where('return_request_id', $return->id)->get()->keyBy('type');

    expect($reversals->keys()->all())->toEqualCanonicalizing(['return_reversal', 'commission_reversal', 'tcs_reversal', 'tds_reversal'])
        ->and($reversals['return_reversal']->direction)->toBe('debit')
        ->and($reversals['return_reversal']->amount->toDecimal())->toBe('1050.00')
        ->and($reversals['commission_reversal']->direction)->toBe('credit')
        ->and($reversals['commission_reversal']->amount->toDecimal())->toBe('105.00')
        ->and($reversals['commission_reversal']->tax_amount->toDecimal())->toBe('18.90')
        ->and($reversals['tcs_reversal']->amount->toDecimal())->toBe('10.00')
        ->and($reversals['tds_reversal']->amount->toDecimal())->toBe('1.05')
        // The sale was still in its return window: the reversal nets out in the same payout.
        ->and($reversals['return_reversal']->status)->toBe(SellerLedgerEntry::STATUS_PENDING)
        ->and($reversals['return_reversal']->eligible_on->toDateString())->toBe(ledgerByType($sellerOrder)['sale']->eligible_on->toDateString());

    $net = SellerLedgerEntry::query()->where('seller_order_id', $sellerOrder->id)->get()->reduce(fn (Money $carry, SellerLedgerEntry $entry) => $carry->plus($entry->signedValue()), Money::zero());

    // One of two units kept: 1050 − 105 − 18.90 − 10 − 1.05.
    expect($net->toDecimal())->toBe('915.05');
});

test('an RTO before delivery posts nothing unless the seller pays RTO shipping', function () {
    $sellerOrder = rtoDeliveredOrder(financeSeller());

    expect(SellerLedgerEntry::query()->where('seller_order_id', $sellerOrder->id)->count())->toBe(0);

    financeSettings(['seller_pays_rto_shipping' => 1]);
    Shipment::query()->where('seller_order_id', $sellerOrder->id)->update(['freight_charge' => '60.00', 'rto_charge' => '45.00']);

    app(LedgerPosterImpl::class)->postRto(Shipment::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail());
    $entries = ledgerByType($sellerOrder);

    expect(array_keys($entries))->toBe(['logistics_rto'])
        ->and($entries['logistics_rto']->amount->toDecimal())->toBe('105.00')
        ->and($entries['logistics_rto']->direction)->toBe('debit');
});

test('seller-paid forward shipping is deducted on delivery', function () {
    financeSettings(['seller_pays_forward_shipping' => 1]);
    $seller = financeSeller();
    $item = cartVariant(['price' => '1050', 'mrp' => '1050', 'stock' => 5, 'weight_grams' => 400], $seller, ['gst_percent' => '5']);
    $customer = orderCustomer();
    orderCart($customer, [[$item, 1]]);
    $result = placeOrder($customer, orderCheckout($customer));
    $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->firstOrFail();
    fulfilment()->accept($sellerOrder);
    Shipment::query()->where('seller_order_id', $sellerOrder->id)->update(['freight_charge' => '55.00', 'cod_charge' => '20.00']);
    fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

    expect(ledgerByType($sellerOrder)['logistics_forward']->amount->toDecimal())->toBe('75.00');
});

test('seller penalties and accepted claims reach the ledger as payable entries', function () {
    financeSettings(['seller_cancel_penalty' => 50]);
    [, $sellerOrder] = financeDeliveredOrder();

    SellerPenaltyIncurred::dispatch(SellerOrder::query()->findOrFail($sellerOrder->id), SellerPenaltyIncurred::KIND_SELLER_CANCEL, 'Out of stock');

    $penalty = ledgerByType($sellerOrder)['penalty'];
    expect($penalty->amount->toDecimal())->toBe('50.00')
        ->and($penalty->direction)->toBe('debit')
        ->and($penalty->status)->toBe(SellerLedgerEntry::STATUS_ELIGIBLE);

    $claim = ReturnClaim::query()->forceCreate([
        'seller_order_id' => $sellerOrder->id,
        'seller_id' => $sellerOrder->seller_id,
        'source' => ReturnClaim::SOURCE_RTO,
        'reason' => 'damaged_return',
        'status' => ReturnClaim::STATUS_OPEN,
    ]);
    app(ReturnClaimService::class)->decide($claim, true, Money::of('200'), 'Approved', financeAdmin());

    $credit = SellerLedgerEntry::query()->where('idempotency_key', 'claim_credit:claim:'.$claim->id)->firstOrFail();
    expect($credit->direction)->toBe('credit')->and($credit->amount->toDecimal())->toBe('200.00');
});

test('the confirmed order records the commission rule behind each item snapshot', function () {
    $seller = financeSeller();
    $rule = CommissionRule::factory()->forSeller($seller)->percent('8')->create();

    [, $sellerOrder] = financeDeliveredOrder($seller, '500');
    $item = $sellerOrder->items()->firstOrFail();

    expect($item->commission_rule_id)->toBe($rule->id)
        ->and($item->commission_amount->toDecimal())->toBe('40.00');
});
