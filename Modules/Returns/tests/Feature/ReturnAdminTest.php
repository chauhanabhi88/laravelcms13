<?php

use Illuminate\Support\Facades\Event;
use Modules\Order\DTO\CheckoutSession;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Events\ReturnClaimDecided;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

test('the console lists, filters, shows and exports returns', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);
    $admin = returnsAdmin();

    $this->actingAs($admin)->get(route('admin.return_request.index', updateUrlParams()))->assertSuccessful()->assertSee($return->return_no);
    $this->postJson(route('admin.return_request.filters', updateUrlParams()), ['status' => 'requested'])->assertSuccessful()->assertJsonPath('type', 'success');
    $this->get(route('admin.return_request.show', updateUrlParams([$return->id])))->assertSuccessful()->assertSee($return->return_no)->assertSee('buyer@okbank');

    $csv = $this->get(route('admin.return_request.export', updateUrlParams()))->assertSuccessful()->streamedContent();

    expect($csv)->toContain($return->return_no);
});

test('CX approves a held return and the pickup is booked', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    $this->actingAs(returnsAdmin())
        ->post(route('admin.return_request.approve', updateUrlParams([$return->id])), ['note' => 'Looks fine'])
        ->assertRedirect(route('admin.return_request.show', updateUrlParams([$return->id])))
        ->assertSessionHas('success');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_PICKUP_SCHEDULED)
        ->and($return->decided_by)->toBe('admin')
        ->and($return->isAutoApproved())->toBeFalse();
});

test('CX rejects with a reason', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    $this->actingAs(returnsAdmin())
        ->post(route('admin.return_request.reject', updateUrlParams([$return->id])), [])
        ->assertSessionHasErrors('note');

    $this->post(route('admin.return_request.reject', updateUrlParams([$return->id])), ['note' => 'Outside our policy'])
        ->assertSessionHas('success');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_REJECTED)
        ->and($return->decision_note)->toBe('Outside our policy');
});

test('CX refunds a failed QC with the customer\'s COD details overridden', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '600']);
    $return = raiseReturn($sellerOrder, $customer);
    app(ReturnService::class)->approve($return);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    app(ReturnService::class)->qcFail($return->refresh(), 'Stained');

    $this->actingAs(returnsAdmin())
        ->post(route('admin.return_request.force_refund', updateUrlParams([$return->id])), [
            'amount' => '250',
            'refund_mode' => 'bank',
            'account_holder' => 'Asha',
            'account_number' => '123456789012',
            'account_number_confirmation' => '123456789012',
            'ifsc' => 'HDFC0001234',
            'note' => 'Goodwill partial refund',
        ])
        ->assertSessionHas('success');

    $refund = PaymentRefund::query()->where('return_request_id', $return->id)->firstOrFail();

    expect($refund->mode)->toBe(PaymentRefund::MODE_BANK)
        ->and($refund->amount->toDecimal())->toBe('250.00')
        ->and($return->refresh()->status)->toBe(ReturnRequest::STATUS_REFUND_INITIATED);
});

test('a refund above the return amount is refused', function () {
    [, $sellerOrder, , $customer] = deliveredOrder(['price' => '600'], 1, CheckoutSession::METHOD_PREPAID);
    $return = raiseReturn($sellerOrder, $customer);
    app(ReturnService::class)->approve($return);
    reverseScans($return, ['picked_up', 'in_transit', 'delivered']);
    app(ReturnService::class)->qcFail($return->refresh(), 'Stained');

    $this->actingAs(returnsAdmin())
        ->post(route('admin.return_request.force_refund', updateUrlParams([$return->id])), ['amount' => '900', 'note' => 'Too much'])
        ->assertSessionHas('error');

    expect(PaymentRefund::query()->where('return_request_id', $return->id)->exists())->toBeFalse();
});

test('admins without the permission are refused', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    $this->actingAs(returnsAdmin(['admin.return_request.index', 'admin.return_request.show']))
        ->post(route('admin.return_request.approve', updateUrlParams([$return->id])))
        ->assertForbidden();
});

test('CX accepts a claim with the amount to credit', function () {
    $claim = ReturnClaim::factory()->create();

    $this->actingAs(returnsAdmin())
        ->get(route('admin.return_claim.index', updateUrlParams()))
        ->assertSuccessful();
    $this->get(route('admin.return_claim.show', updateUrlParams([$claim->id])))->assertSuccessful();

    $this->post(route('admin.return_claim.decide', updateUrlParams([$claim->id])), ['decision' => 'accept'])
        ->assertSessionHasErrors('resolution_amount');

    Event::fake([ReturnClaimDecided::class]);

    $this->post(route('admin.return_claim.decide', updateUrlParams([$claim->id])), ['decision' => 'accept', 'resolution_amount' => '199', 'note' => 'Damage confirmed'])
        ->assertSessionHas('success');

    expect($claim->refresh()->status)->toBe(ReturnClaim::STATUS_ACCEPTED)
        ->and($claim->resolution_amount->toDecimal())->toBe('199.00');

    Event::assertDispatched(ReturnClaimDecided::class);
});
