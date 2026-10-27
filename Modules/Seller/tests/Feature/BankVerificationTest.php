<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Seller\Events\SellerBankAccountChanged;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Services\BankAccountService;

require_once __DIR__.'/../Helpers.php';

const RAZORPAYX_SECRET = 'whsec_test_123';

beforeEach(function () {
    Storage::fake('private');
    config([
        'seller.defaults.bank_verifier' => 'razorpayx',
        'seller.razorpayx.key_id' => 'rzp_test_key',
        'seller.razorpayx.key_secret' => 'rzp_test_secret',
        'seller.razorpayx.account_number' => '7878780080316316',
        'seller.razorpayx.webhook_secret' => RAZORPAYX_SECRET,
    ]);

    Http::fake([
        'ifsc.razorpay.com/*' => Http::response(['BANK' => 'HDFC Bank', 'BRANCH' => 'Koramangala', 'CITY' => 'Bengaluru', 'STATE' => 'Karnataka', 'ADDRESS' => '', 'IFSC' => 'HDFC0001234']),
        'api.razorpay.com/v1/contacts' => Http::response(['id' => 'cont_00000000000001', 'entity' => 'contact']),
        'api.razorpay.com/v1/fund_accounts' => Http::response(['id' => 'fa_00000000000001', 'entity' => 'fund_account']),
        'api.razorpay.com/v1/fund_accounts/validations' => Http::response([
            'id' => 'fav_00000000000001',
            'entity' => 'fund_account.validation',
            'status' => 'created',
            'results' => ['account_status' => null, 'registered_name' => null],
        ]),
    ]);

    [$this->seller, $this->owner] = sellerWithOwner('onboarding');
    $this->seller->update(['legal_name' => 'Indigo Loom Textiles', 'store_name' => 'Indigo Loom']);
});

function bankDetails(): array
{
    return [
        'account_holder_name' => 'Indigo Loom Textiles',
        'account_number' => '50100123456789',
        'ifsc' => 'HDFC0001234',
        'account_type' => 'current',
        'bank_name' => 'HDFC Bank',
        'branch' => 'Koramangala',
    ];
}

/**
 * POST a fund_account.validation webhook, signed unless $signature is given.
 */
function razorpayxWebhook(string $event, array $validation, ?string $signature = null)
{
    $body = json_encode([
        'entity' => 'event',
        'event' => $event,
        'contains' => ['fund_account.validation'],
        'payload' => ['fund_account.validation' => ['entity' => $validation]],
    ]);

    return test()->call('POST', route('webhook.razorpayx'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, RAZORPAYX_SECRET),
        'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_'.uniqid(),
    ], $body);
}

function completedValidation(string $name, string $status = 'active'): array
{
    return [
        'id' => 'fav_00000000000001',
        'entity' => 'fund_account.validation',
        'fund_account' => ['id' => 'fa_00000000000001'],
        'status' => 'completed',
        'results' => ['account_status' => $status, 'registered_name' => $name],
    ];
}

test('a RazorpayX validation is created and the account waits for the webhook', function () {
    $account = app(BankAccountService::class)->save($this->seller, bankDetails());

    expect($account)
        ->verification_status->toBe(SellerBankAccount::STATUS_PENDING)
        ->verification_method->toBe(SellerBankAccount::METHOD_PENNY_DROP)
        ->provider_ref->toBe('fav_00000000000001')
        ->razorpayx_contact_id->toBe('cont_00000000000001')
        ->razorpayx_fund_account_id->toBe('fa_00000000000001');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.razorpay.com/v1/fund_accounts/validations'
        && $request['fund_account']['id'] === 'fa_00000000000001'
        && $request['account_number'] === '7878780080316316'
        && $request['amount'] === 100
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:rzp_test_secret')));

    // The onboarding page polls this endpoint.
    $this->actingAs($this->owner, 'seller')
        ->getJson(route('seller.onboarding.bank_status'))
        ->assertSuccessful()
        ->assertJson(['status' => 'pending', 'done' => false]);
});

test('a completed webhook with a matching name verifies the account', function () {
    app(BankAccountService::class)->save($this->seller, bankDetails());

    razorpayxWebhook('fund_account.validation.completed', completedValidation('INDIGO LOOM TEXTILES'))
        ->assertSuccessful();

    $account = SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole();
    expect($account)
        ->verification_status->toBe(SellerBankAccount::STATUS_VERIFIED)
        ->verified_name->toBe('INDIGO LOOM TEXTILES')
        ->name_match_score->toBe(100)
        ->verified_at->not->toBeNull();

    $this->actingAs($this->owner, 'seller')
        ->getJson(route('seller.onboarding.bank_status'))
        ->assertJson(['status' => 'verified', 'done' => true]);
});

test('a name mismatch goes to manual review', function () {
    app(BankAccountService::class)->save($this->seller, bankDetails());

    razorpayxWebhook('fund_account.validation.completed', completedValidation('SURESH KUMAR'))->assertSuccessful();

    expect(SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole())
        ->verification_status->toBe(SellerBankAccount::STATUS_MANUAL_REVIEW)
        ->verified_name->toBe('SURESH KUMAR')
        ->name_match_score->toBeLessThan(80);
});

test('an invalid account fails', function () {
    app(BankAccountService::class)->save($this->seller, bankDetails());

    razorpayxWebhook('fund_account.validation.completed', completedValidation('INDIGO LOOM TEXTILES', 'invalid'))->assertSuccessful();

    expect(SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole()->verification_status)
        ->toBe(SellerBankAccount::STATUS_FAILED);
});

test('a webhook with a bad signature is refused and changes nothing', function () {
    app(BankAccountService::class)->save($this->seller, bankDetails());

    razorpayxWebhook('fund_account.validation.completed', completedValidation('INDIGO LOOM TEXTILES'), 'not-a-signature')
        ->assertUnauthorized();

    expect(SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole()->verification_status)
        ->toBe(SellerBankAccount::STATUS_PENDING);
});

test('a redelivered webhook does not change a settled account', function () {
    app(BankAccountService::class)->save($this->seller, bankDetails());

    razorpayxWebhook('fund_account.validation.completed', completedValidation('INDIGO LOOM TEXTILES'));
    razorpayxWebhook('fund_account.validation.failed', ['id' => 'fav_00000000000001', 'status' => 'failed'])->assertSuccessful();

    expect(SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole()->verification_status)
        ->toBe(SellerBankAccount::STATUS_VERIFIED);
});

test('when RazorpayX is unreachable the account falls back to manual review', function () {
    // Drop the beforeEach stubs: earlier stubs win over later ones.
    Http::swap(new HttpFactory);
    Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Server error']], 503)]);

    $account = app(BankAccountService::class)->save($this->seller, bankDetails());

    expect($account)
        ->verification_status->toBe(SellerBankAccount::STATUS_MANUAL_REVIEW)
        ->verification_method->toBe(SellerBankAccount::METHOD_CHEQUE);
});

test('the manual driver asks for a cancelled cheque', function () {
    config(['seller.defaults.bank_verifier' => 'manual']);
    $service = app(BankAccountService::class);

    $account = $service->save($this->seller, bankDetails());
    expect($account->verification_status)->toBe(SellerBankAccount::STATUS_MANUAL_REVIEW)
        ->and($account->isAcceptableForSubmission())->toBeFalse();

    $account = $service->save($this->seller, bankDetails(), UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf'));
    expect($account->cheque_document_id)->not->toBeNull()
        ->and($account->isAcceptableForSubmission())->toBeTrue()
        ->and(SellerBankAccount::query()->where('seller_id', $this->seller->id)->count())->toBe(1);
});

test('an approved seller changing bank details gets a new account and a payout hold', function () {
    Event::fake([SellerBankAccountChanged::class]);
    $fake = useFakeBankVerifier();
    [$seller, $owner] = sellerWithOwner('active');
    $old = SellerBankAccount::factory()->for($seller)->verified()->create();
    $seller->update(['payout_hold' => config('core.no')]);
    $fake->stayPending();

    $this->actingAs($owner, 'seller')
        ->post(route('seller.bank.store'), [
            'account_holder_name' => $seller->legal_name,
            'account_number' => '00112233445566',
            'account_number_confirmation' => '00112233445566',
            'ifsc' => 'HDFC0001234',
            'account_type' => 'current',
        ])
        ->assertRedirect(route('seller.bank.index'));

    expect($old->fresh()->is_primary)->toBe(2)
        ->and($seller->fresh()->payout_hold)->toBe(1)
        ->and($seller->primaryBankAccount()->first())
        ->account_last4->toBe('5566')
        ->verification_status->toBe(SellerBankAccount::STATUS_PENDING);

    Event::assertDispatched(SellerBankAccountChanged::class);

    $this->actingAs($owner, 'seller')->get(route('seller.bank.index'))->assertSuccessful()->assertSee('XXXXXX5566');
});
