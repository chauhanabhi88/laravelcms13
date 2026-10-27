<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\StatusHistory;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerSubmittedForVerification;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Storage::fake('private');
    fakeIfscDirectory();
    useFakeBankVerifier();
    [$this->seller, $this->owner] = sellerWithOwner('onboarding');
    $this->actingAs($this->owner, 'seller');
});

function businessStep(array $overrides = []): array
{
    return array_merge([
        'store_name' => 'Indigo Loom',
        'legal_name' => 'Indigo Loom Textiles',
        'business_type' => 'proprietorship',
        'pan' => 'abcpe1234f',
        'email' => 'hello@indigoloom.test',
    ], $overrides);
}

function bankStep(array $overrides = []): array
{
    return array_merge([
        'account_holder_name' => 'Indigo Loom Textiles',
        'account_number' => '50100123456789',
        'account_number_confirmation' => '50100123456789',
        'ifsc' => 'hdfc0001234',
        'account_type' => 'current',
    ], $overrides);
}

function warehouseStep(array $overrides = []): array
{
    return array_merge([
        'name' => 'Main warehouse',
        'contact_name' => 'Ravi Kumar',
        'contact_phone' => '9845012345',
        'address_line1' => '14, 3rd Cross, Koramangala',
        'pincode' => '560034',
    ], $overrides);
}

function gstCertificate(): UploadedFile
{
    return UploadedFile::fake()->create('gst-certificate.pdf', 200, 'application/pdf');
}

test('an onboarding seller is sent from the dashboard to the wizard', function () {
    $this->get(route('seller.dashboard'))->assertRedirect(route('seller.onboarding.show', [1]));
    $this->get(route('seller.onboarding.show'))->assertRedirect(route('seller.onboarding.show', [1]));
    $this->get(route('seller.onboarding.show', [1]))->assertSuccessful()->assertSee(trans('seller::onboarding.business.heading'));
});

test('steps cannot be skipped', function () {
    $this->get(route('seller.onboarding.show', [4]))->assertRedirect(route('seller.onboarding.show', [1]));
    $this->post(route('seller.onboarding.save', [3]), bankStep())->assertRedirect(route('seller.onboarding.show', [1]));

    expect(SellerBankAccount::query()->where('seller_id', $this->seller->id)->exists())->toBeFalse();
});

test('step 1 saves business details with an encrypted PAN and a store slug', function () {
    $this->post(route('seller.onboarding.save', [1]), businessStep())
        ->assertRedirect(route('seller.onboarding.show', [2]));

    $seller = $this->seller->fresh();
    $raw = DB::table('seller')->where('id', $seller->id)->value('pan');

    expect($seller)
        ->store_name->toBe('Indigo Loom')
        ->store_slug->toBe('indigo-loom')
        ->pan->toBe('ABCPE1234F')
        ->pan_last4->toBe('234F')
        ->onboarding_step->toBe(2)
        ->and($raw)->not->toContain('ABCPE1234F');
});

test('step 1 validates PAN and store name uniqueness', function () {
    Seller::factory()->active()->create(['store_name' => 'Indigo Loom']);

    $this->post(route('seller.onboarding.save', [1]), businessStep(['pan' => 'ABCDE1234F']))
        ->assertSessionHasErrors(['pan', 'store_name']);

    expect($this->seller->fresh()->onboarding_step)->toBe(1);
});

test('step 2 checks the GSTIN against the PAN and stores the certificate privately', function () {
    $stateId = sellerTestState('KA');
    $this->post(route('seller.onboarding.save', [1]), businessStep());

    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '27AAPFU0939F1ZV', 'gst_certificate' => gstCertificate()])
        ->assertSessionHasErrors('gstin');

    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '29ABCPE1234F1Z7'])
        ->assertSessionHasErrors('gst_certificate');

    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '29abcpe1234f1z7', 'gst_certificate' => gstCertificate()])
        ->assertRedirect(route('seller.onboarding.show', [3]));

    $seller = $this->seller->fresh();
    $document = SellerKycDocument::query()->where('seller_id', $seller->id)->sole();

    expect($seller)
        ->gstin->toBe('29ABCPE1234F1Z7')
        ->gst_status->toBe(Seller::GST_UNVERIFIED)
        ->state_id->toBe($stateId)
        ->and($document)->type->toBe('gst_certificate')->disk->toBe('private');

    Storage::disk('private')->assertExists($document->path);
    expect($document->path)->toStartWith('seller_kyc/'.$seller->id.'/');
});

test('progress is kept and the wizard resumes where the seller left off', function () {
    $this->post(route('seller.onboarding.save', [1]), businessStep());
    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '29ABCPE1234F1Z7', 'gst_certificate' => gstCertificate()]);

    $this->get(route('seller.onboarding.show'))->assertRedirect(route('seller.onboarding.show', [3]));

    // Going back to an earlier step is allowed and keeps the saved values.
    $this->get(route('seller.onboarding.show', [1]))->assertSuccessful()->assertSee('Indigo Loom Textiles');
});

test('step 3 verifies the bank account and step 4 needs a serviceable pincode', function () {
    sellerTestPincode('560034');
    sellerTestPincode('560099', serviceable: false);
    $this->post(route('seller.onboarding.save', [1]), businessStep());
    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '29ABCPE1234F1Z7', 'gst_certificate' => gstCertificate()]);

    $this->post(route('seller.onboarding.save', [3]), bankStep(['ifsc' => 'HDFC0009999']))->assertSessionHasErrors('ifsc');
    $this->post(route('seller.onboarding.save', [3]), bankStep(['account_number_confirmation' => '50100123456780']))->assertSessionHasErrors('account_number');

    $this->post(route('seller.onboarding.save', [3]), bankStep())->assertRedirect(route('seller.onboarding.show', [4]));

    $account = SellerBankAccount::query()->where('seller_id', $this->seller->id)->sole();
    expect($account)
        ->verification_status->toBe(SellerBankAccount::STATUS_VERIFIED)
        ->account_last4->toBe('6789')
        ->bank_name->toBe('HDFC Bank')
        ->branch->toBe('Koramangala')
        ->name_match_score->toBe(100)
        ->is_primary->toBe(1);

    $this->post(route('seller.onboarding.save', [4]), warehouseStep(['pincode' => '999999']))->assertSessionHasErrors('pincode');
    $this->post(route('seller.onboarding.save', [4]), warehouseStep(['pincode' => '560099']))->assertSessionHasErrors('pincode');

    $this->post(route('seller.onboarding.save', [4]), warehouseStep())->assertRedirect(route('seller.onboarding.show', [5]));

    $warehouse = SellerWarehouse::query()->where('seller_id', $this->seller->id)->sole();
    expect($warehouse)
        ->is_default->toBe(1)
        ->contact_phone->toBe('+919845012345')
        ->state_name->toBe('Karnataka')
        ->city_name->toBe('Bengaluru Urban');
});

test('the application cannot be submitted with steps missing', function () {
    $this->post(route('seller.onboarding.save', [1]), businessStep());
    $this->seller->update(['onboarding_step' => 5]);

    $this->post(route('seller.onboarding.save', [5]), ['accept_agreement' => '1', 'agreement_version' => config('seller.agreement_version')])
        ->assertSessionHas('error');

    expect($this->seller->fresh()->status)->toBe(SellerStatus::Onboarding);
});

test('a complete application is submitted for verification', function () {
    Event::fake([SellerSubmittedForVerification::class]);
    sellerTestPincode('560034');

    $this->post(route('seller.onboarding.save', [1]), businessStep());
    $this->post(route('seller.onboarding.save', [2]), ['gstin' => '29ABCPE1234F1Z7', 'gst_certificate' => gstCertificate()]);
    $this->post(route('seller.onboarding.save', [3]), bankStep());
    $this->post(route('seller.onboarding.save', [4]), warehouseStep());

    $this->get(route('seller.onboarding.show', [5]))->assertSuccessful()->assertSee(trans('seller::agreement.title'));

    $this->post(route('seller.onboarding.save', [5]), ['agreement_version' => config('seller.agreement_version')])
        ->assertSessionHasErrors('accept_agreement');

    $this->post(route('seller.onboarding.save', [5]), ['accept_agreement' => '1', 'agreement_version' => config('seller.agreement_version')])
        ->assertRedirect(route('seller.onboarding.status'));

    $seller = $this->seller->fresh();

    expect($seller)
        ->status->toBe(SellerStatus::PendingVerification)
        ->agreement_version->toBe(config('seller.agreement_version'))
        ->agreement_accepted_ip->toBe('127.0.0.1')
        ->submitted_at->not->toBeNull();

    $history = StatusHistory::query()->where('model_type', Seller::class)->where('model_id', $seller->id)->sole();
    expect($history)->from_status->toBe('onboarding')->to_status->toBe('pending_verification')->actor_type->toBe('seller')->actor_id->toBe($this->owner->id);

    Event::assertDispatched(SellerSubmittedForVerification::class, fn ($event) => $event->seller->is($seller));

    $this->get(route('seller.onboarding.status'))->assertSuccessful()->assertSee(trans('seller::onboarding.status.pending_verification.heading'));
    $this->get(route('seller.dashboard'))->assertRedirect(route('seller.onboarding.status'));
    $this->get(route('seller.onboarding.show', [1]))->assertRedirect(route('seller.onboarding.status'));
});

test('a seller without GST registers with an enrolment ID when the setting allows it', function () {
    config(['seller.defaults.allow_non_gst_sellers' => 1]);
    $this->post(route('seller.onboarding.save', [1]), businessStep());

    $this->post(route('seller.onboarding.save', [2]), ['has_gst' => 2, 'enrolment_id' => 'enr12345678'])
        ->assertRedirect(route('seller.onboarding.show', [3]));

    expect($this->seller->fresh())
        ->gstin->toBeNull()
        ->enrolment_id->toBe('ENR12345678')
        ->gst_status->toBe(Seller::GST_NOT_APPLICABLE);
});

test('without the setting a GSTIN is always required', function () {
    $this->post(route('seller.onboarding.save', [1]), businessStep());

    $this->post(route('seller.onboarding.save', [2]), ['has_gst' => 2, 'enrolment_id' => 'ENR12345678'])
        ->assertSessionHasErrors('gstin');
});

test('a rejected seller sees the reason and can start again', function () {
    $this->seller->forceFill(['status' => SellerStatus::Rejected, 'status_reason' => 'Documents unreadable'])->save();

    $this->get(route('seller.dashboard'))->assertRedirect(route('seller.onboarding.status'));
    $this->get(route('seller.onboarding.status'))->assertSuccessful()->assertSee('Documents unreadable');

    $this->post(route('seller.onboarding.restart'))->assertRedirect(route('seller.onboarding.show', [1]));

    expect($this->seller->fresh()->status)->toBe(SellerStatus::Onboarding);
});
