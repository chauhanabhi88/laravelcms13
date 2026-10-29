<?php

use Modules\Core\Support\Money;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\ResellerWalletEntry;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Services\ResellerWalletService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    $this->wallet = app(ResellerWalletService::class);
});

test('the margin of a delivered COD reseller order waits for the return window, then becomes available', function () {
    [$sellerOrder, $reseller] = deliveredResellerOrder();
    $credit = ResellerWalletEntry::query()->forCustomer($reseller->id)->firstOrFail();

    expect($credit->type)->toBe(ResellerWalletEntry::TYPE_MARGIN_CREDIT)
        ->and($credit->amount->toDecimal())->toBe('100.00')
        ->and($credit->status)->toBe(ResellerWalletEntry::STATUS_PENDING)
        ->and($credit->eligible_on->toDateString())->toBe($sellerOrder->return_window_ends_at->toDateString())
        ->and($this->wallet->balances($reseller->id)['pending']->toDecimal())->toBe('100.00');

    expect($this->wallet->release(now()->addDays(60)))->toBe(1)
        ->and($this->wallet->balances($reseller->id)['available']->toDecimal())->toBe('100.00');
});

test('a refunded return reverses the margin of the returned unit', function () {
    [$sellerOrder, $reseller] = deliveredResellerOrder();
    financeRefundedReturn($sellerOrder, $reseller);

    $reversal = ResellerWalletEntry::query()->forCustomer($reseller->id)->where('type', ResellerWalletEntry::TYPE_REVERSAL)->firstOrFail();

    expect($reversal->amount->toDecimal())->toBe('50.00')
        ->and($reversal->status)->toBe(ResellerWalletEntry::STATUS_PENDING)
        ->and($this->wallet->balances($reseller->id)['pending']->toDecimal())->toBe('50.00');
});

test('withdrawals debit the wallet at once; paid on success, credited back on failure', function () {
    [, $reseller] = deliveredResellerOrder();
    $this->wallet->release(now()->addDays(60));

    $withdrawal = $this->wallet->withdraw($reseller, Money::of('100'), 'upi', ['vpa' => 'asha@okbank']);

    expect($withdrawal->status)->toBe(ResellerWithdrawal::STATUS_PROCESSING)
        ->and($withdrawal->beneficiary_label)->toBe('as•••@okbank')
        ->and(fakePayouts()->lastRequest()->beneficiary['vpa'])->toBe('asha@okbank')
        ->and($this->wallet->balances($reseller->id)['available']->toDecimal())->toBe('0.00');

    $this->wallet->markPaid($withdrawal, 'UPI1234567');

    expect($withdrawal->refresh()->status)->toBe(ResellerWithdrawal::STATUS_PAID)
        ->and($this->wallet->balances($reseller->id)['paid']->toDecimal())->toBe('100.00');
});

test('a failed withdrawal returns the money to the wallet', function () {
    [, $reseller] = deliveredResellerOrder();
    $this->wallet->release(now()->addDays(60));
    fakePayouts()->mode = 'failed';

    $withdrawal = $this->wallet->withdraw($reseller, Money::of('100'), 'bank', ['account_holder' => 'Asha', 'account_number' => '123456789012', 'ifsc' => 'HDFC0001234']);

    expect($withdrawal->status)->toBe(ResellerWithdrawal::STATUS_FAILED)
        ->and($this->wallet->balances($reseller->id)['available']->toDecimal())->toBe('100.00');
});

test('withdrawals need a reseller, the minimum and enough available balance', function () {
    [, $reseller] = deliveredResellerOrder();

    expect(fn () => $this->wallet->withdraw($reseller, Money::of('100'), 'upi', ['vpa' => 'a@ok']))->toThrow(FinanceException::class, trans('finance::wallet.errors.insufficient'))
        ->and(fn () => $this->wallet->withdraw($reseller, Money::of('50'), 'upi', ['vpa' => 'a@ok']))->toThrow(FinanceException::class)
        ->and(fn () => $this->wallet->withdraw(orderCustomer(), Money::of('100'), 'upi', ['vpa' => 'a@ok']))->toThrow(FinanceException::class, trans('finance::wallet.errors.not_reseller'));
});

test('the earnings page shows balances and accepts a withdrawal', function () {
    [, $reseller] = deliveredResellerOrder();
    $this->wallet->release(now()->addDays(60));

    $this->actingAs($reseller, 'customer')->get(frontRoute('customer.earnings.index'))
        ->assertOk()
        ->assertSee('data-balance="available"', false)
        ->assertSee('data-withdraw-form', false);

    $this->actingAs($reseller, 'customer')->post(frontRoute('customer.earnings.withdraw'), ['amount' => '100', 'refund_mode' => 'upi', 'vpa' => 'asha@okbank'])
        ->assertRedirect(frontRoute('customer.earnings.index'))
        ->assertSessionHas('success');

    expect(ResellerWithdrawal::query()->forCustomer($reseller->id)->count())->toBe(1);
});
