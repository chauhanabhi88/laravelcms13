<?php

use Illuminate\Support\Facades\Storage;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Support\ReturnDraft;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    returnsSetup();
    returnsSettings(['auto_approve' => 2]);
});

/**
 * The wizard draft in the session.
 *
 * @return array<string, mixed>
 */
function wizardSession(ReturnDraft $draft): array
{
    return [ReturnDraft::key() => $draft->toArray()];
}

function reasonDone(SalesOrder $order, SellerOrder $sellerOrder, string $reason = 'wrong_size'): ReturnDraft
{
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    return new ReturnDraft($order->order_no, $sellerOrder->id, 'return', $reason, null, [$item->id => ['quantity' => 1, 'exchange_variant_id' => null]]);
}

test('step 1 shows the returnable items of the package', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $sellerOrder->id]]))
        ->assertSuccessful()
        ->assertSee($item->name)
        ->assertSee('data-step="reason"', false)
        ->assertSessionHas(ReturnDraft::key().'.seller_order_id', $sellerOrder->id);
});

test('an undelivered package sends the customer back to the order', function () {
    [$order] = pendingFulfilmentOrder();

    $this->actingAs($order->customer()->firstOrFail(), 'customer')
        ->get(frontRoute('customer.returns.create', ['orderNo' => $order->order_no]))
        ->assertRedirect(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSessionHas('error');
});

test('step 1 saves the items and reason into the draft', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder([], 2);
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession(new ReturnDraft($order->order_no, $sellerOrder->id)))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'reason']), [
            'type' => 'return',
            'reason_code' => 'quality',
            'comment' => 'Thin fabric',
            'items' => [$item->id => ['selected' => '1', 'quantity' => 2]],
        ])
        ->assertRedirect(frontRoute('customer.returns.step', ['step' => 'photos']))
        ->assertSessionHas(ReturnDraft::key().'.items.'.$item->id.'.quantity', 2)
        ->assertSessionHas(ReturnDraft::key().'.reason_code', 'quality');
});

test('step 1 refuses more than the remaining quantity', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession(new ReturnDraft($order->order_no, $sellerOrder->id)))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'reason']), [
            'type' => 'return',
            'reason_code' => 'quality',
            'items' => [$item->id => ['selected' => '1', 'quantity' => 3]],
        ])
        ->assertSessionHasErrors('items');
});

test('step 2 stores re-encoded photos and requires them for a damaged item', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $draft = reasonDone($order, $sellerOrder, 'damaged');

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession($draft))
        ->get(frontRoute('customer.returns.step', ['step' => 'photos']))
        ->assertSuccessful()
        ->assertSee('data-step="photos"', false);

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'photos']), [])
        ->assertSessionHasErrors('photos');

    $response = $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'photos']), ['photos' => [returnPhoto(), returnPhoto('b.png')]])
        ->assertRedirect(frontRoute('customer.returns.step', ['step' => 'refund']));

    $photos = $response->getSession()->get(ReturnDraft::key().'.photos');

    expect($photos)->toHaveCount(2)
        ->and($photos[0]['path'])->toStartWith('returns/drafts/'.$customer->id.'/')
        ->and($photos[1]['path'])->toEndWith('.jpg')
        ->and(Storage::disk('private')->exists($photos[0]['path']))->toBeTrue();
});

test('step 3 validates the UPI id of a COD return', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $draft = reasonDone($order, $sellerOrder);
    $draft->photosDone = true;

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), ['refund_mode' => 'upi', 'vpa' => 'not-a-vpa'])
        ->assertSessionHasErrors('vpa');

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), [])
        ->assertSessionHasErrors('refund_mode');

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), ['refund_mode' => 'upi', 'vpa' => 'asha@okbank'])
        ->assertRedirect(frontRoute('customer.returns.step', ['step' => 'confirm']))
        ->assertSessionHas(ReturnDraft::key().'.refund_mode', 'upi');
});

test('step 3 validates a bank account and its IFSC', function () {
    fakeIfscDirectory(['HDFC0001234']);
    [$order, $sellerOrder, , $customer] = deliveredOrder();
    $draft = reasonDone($order, $sellerOrder);
    $draft->photosDone = true;
    $bank = ['refund_mode' => 'bank', 'account_holder' => 'Asha', 'account_number' => '123456789012', 'account_number_confirmation' => '123456789012'];

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), $bank + ['ifsc' => 'HDFC0009999'])
        ->assertSessionHasErrors('ifsc');

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), ['account_number_confirmation' => '1'] + $bank + ['ifsc' => 'HDFC0001234'])
        ->assertSessionHasErrors('account_number_confirmation');

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), $bank + ['ifsc' => 'HDFC0001234'])
        ->assertRedirect(frontRoute('customer.returns.step', ['step' => 'confirm']));
});

test('a prepaid return skips the refund details', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder(method: CheckoutSession::METHOD_PREPAID);
    $draft = reasonDone($order, $sellerOrder);
    $draft->photosDone = true;

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession($draft))
        ->get(frontRoute('customer.returns.step', ['step' => 'refund']))
        ->assertSuccessful()
        ->assertSee('data-refund-source', false);

    $this->withSession(wizardSession($draft))
        ->post(frontRoute('customer.returns.store_step', ['step' => 'refund']), [])
        ->assertRedirect(frontRoute('customer.returns.step', ['step' => 'confirm']))
        ->assertSessionHas(ReturnDraft::key().'.refund_mode', 'source');
});

test('later steps need the earlier ones', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder();

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession(reasonDone($order, $sellerOrder)))
        ->get(frontRoute('customer.returns.step', ['step' => 'confirm']))
        ->assertRedirect(frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $sellerOrder->id]]));

    $this->flushSession()
        ->get(frontRoute('customer.returns.step', ['step' => 'photos']))
        ->assertRedirect(frontRoute('customer.orders.index'));
});

test('the confirm page shows the refund and submitting raises the return', function () {
    [$order, $sellerOrder, , $customer] = deliveredOrder(['price' => '650']);
    $draft = reasonDone($order, $sellerOrder);
    $draft->photosDone = true;
    $draft->refundMode = 'upi';
    $draft->setBeneficiary(['vpa' => 'asha@okbank']);
    $draft->refundDone = true;

    $this->actingAs($customer, 'customer')
        ->withSession(wizardSession($draft))
        ->get(frontRoute('customer.returns.step', ['step' => 'confirm']))
        ->assertSuccessful()
        ->assertSee('asha@okbank')
        ->assertSee('₹650');

    $response = $this->withSession(wizardSession($draft))->post(frontRoute('customer.returns.submit'));
    $return = ReturnRequest::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    $response->assertRedirect(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]))
        ->assertSessionMissing(ReturnDraft::key());

    expect($return->refund_mode)->toBe('upi')
        ->and($return->refund_beneficiary)->toBe(['vpa' => 'asha@okbank']);

    $this->get(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]))
        ->assertSuccessful()
        ->assertSee($return->return_no)
        ->assertSee('data-timeline', false)
        ->assertSee('data-cancel-return', false);

    $this->get(frontRoute('customer.returns.index'))->assertSuccessful()->assertSee($return->return_no);
});

test('the customer cancels a request from its page', function () {
    [, $sellerOrder, , $customer] = deliveredOrder();
    $return = raiseReturn($sellerOrder, $customer);

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.returns.cancel', ['returnNo' => $return->return_no]))
        ->assertRedirect(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]))
        ->assertSessionHas('success');

    expect($return->refresh()->status)->toBe(ReturnRequest::STATUS_CANCELLED);
});
