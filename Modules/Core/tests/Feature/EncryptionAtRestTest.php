<?php

use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Logistics\Models\Courier;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Models\ReturnRequest;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;

/*
| Plan M21 S8 / NFR-SEC-06: PAN, bank account numbers, refund and payout beneficiaries and
| courier credentials are encrypted at rest. Rotate APP_KEY with APP_PREVIOUS_KEYS so these
| columns stay readable.
*/

test('sensitive columns use encrypted casts', function (string $model, string $column) {
    expect((new $model)->getCasts()[$column] ?? null)->toStartWith('encrypted');
})->with([
    'seller pan' => [Seller::class, 'pan'],
    'bank account number' => [SellerBankAccount::class, 'account_number'],
    'refund beneficiary' => [PaymentRefund::class, 'beneficiary'],
    'return refund beneficiary' => [ReturnRequest::class, 'refund_beneficiary'],
    'reseller withdrawal beneficiary' => [ResellerWithdrawal::class, 'beneficiary'],
    'courier credentials' => [Courier::class, 'credentials'],
]);

test('key rotation keeps previous keys readable', function () {
    expect(config('app'))->toHaveKey('previous_keys');
});
