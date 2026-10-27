<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\StatusHistory;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerRejected;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Notifications\SellerDecisionNotification;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    [$this->seller, $this->owner] = sellerWithOwner('pendingVerification');
    SellerBankAccount::factory()->for($this->seller)->verified()->create(['account_holder_name' => $this->seller->legal_name]);
    SellerWarehouse::factory()->for($this->seller)->create();
    $this->admin = sellerAdmin();
});

function sellerHistory(Seller $seller): ?StatusHistory
{
    return StatusHistory::query()->where('model_type', Seller::class)->where('model_id', $seller->id)->latest('id')->first();
}

test('the KYC queue lists pending applications and shows the detail', function () {
    $onboarding = Seller::factory()->onboarding()->create(['store_name' => 'Not Yet Submitted']);

    $this->actingAs($this->admin)
        ->get(route('admin.seller_application.index'))
        ->assertSuccessful()
        ->assertSee($this->seller->store_name)
        ->assertDontSee($onboarding->store_name);

    $this->actingAs($this->admin)
        ->post(route('admin.seller_application.filters'), ['search' => $this->seller->code])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');

    $this->actingAs($this->admin)
        ->get(route('admin.seller_application.show', [$this->seller->id]))
        ->assertSuccessful()
        ->assertSee($this->seller->gstin)
        ->assertSee(trans('seller::seller.labels.penny_drop_name'))
        ->assertSee(trans('seller::seller.buttons.approve'));
});

test('approving opens the panel, writes history and notifies the seller', function () {
    Notification::fake();
    Event::fake([SellerApproved::class]);

    $this->actingAs($this->admin)
        ->post(route('admin.seller_application.approve', [$this->seller->id]), ['reason' => 'All documents verified'])
        ->assertRedirect(route('admin.seller_application.index'))
        ->assertSessionHas('success');

    $seller = $this->seller->fresh();
    expect($seller)
        ->status->toBe(SellerStatus::Active)
        ->verified_by->toBe($this->admin->id)
        ->verified_at->not->toBeNull()
        ->payout_hold->toBe(2)
        ->and(sellerHistory($seller))
        ->from_status->toBe('pending_verification')
        ->to_status->toBe('active')
        ->actor_type->toBe('admin')
        ->actor_id->toBe($this->admin->id);

    Event::assertDispatched(SellerApproved::class);

    $this->actingAs($this->owner, 'seller')->get(route('seller.dashboard'))->assertSuccessful();
});

test('the approval email reaches the owner', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('admin.seller_application.approve', [$this->seller->id]));

    Notification::assertSentTo($this->owner, SellerDecisionNotification::class, fn ($notification) => $notification->decision === 'approved');
});

test('rejecting needs a reason that the seller then sees', function () {
    Notification::fake();
    Event::fake([SellerRejected::class]);

    $this->actingAs($this->admin)
        ->post(route('admin.seller_application.reject', [$this->seller->id]), ['reason' => ''])
        ->assertSessionHas('error');
    expect($this->seller->fresh()->status)->toBe(SellerStatus::PendingVerification);

    $this->actingAs($this->admin)
        ->post(route('admin.seller_application.reject', [$this->seller->id]), ['reason' => 'GST certificate belongs to another business'])
        ->assertSessionHas('success');

    expect($this->seller->fresh())
        ->status->toBe(SellerStatus::Rejected)
        ->status_reason->toBe('GST certificate belongs to another business')
        ->and(sellerHistory($this->seller)->reason)->toBe('GST certificate belongs to another business');

    Event::assertDispatched(SellerRejected::class, fn ($event) => $event->reason === 'GST certificate belongs to another business');

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.onboarding.status'))
        ->assertSee('GST certificate belongs to another business');
});

test('requesting changes reopens the wizard and the seller can resubmit', function () {
    Notification::fake();
    Event::fake([SellerChangesRequested::class]);
    Storage::fake('private');
    SellerKycDocument::factory()->for($this->seller)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.seller_application.request_changes', [$this->seller->id]), ['reason' => 'Please upload a clearer certificate'])
        ->assertSessionHas('success');

    expect($this->seller->fresh()->status)->toBe(SellerStatus::ChangesRequested);
    Event::assertDispatched(SellerChangesRequested::class);

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.dashboard'))
        ->assertRedirect();

    $this->actingAs($this->owner, 'seller')
        ->get(route('seller.onboarding.show', [1]))
        ->assertSuccessful()
        ->assertSee('Please upload a clearer certificate');

    $this->actingAs($this->owner, 'seller')
        ->post(route('seller.onboarding.save', [5]), ['accept_agreement' => '1', 'agreement_version' => config('seller.agreement_version')])
        ->assertRedirect(route('seller.onboarding.status'));

    expect($this->seller->fresh()->status)->toBe(SellerStatus::PendingVerification);
});

test('decisions need their permissions', function () {
    $viewer = sellerAdmin(['admin.seller_application.index', 'admin.seller_application.show']);

    $this->actingAs($viewer)->get(route('admin.seller_application.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('admin.seller_application.approve', [$this->seller->id]))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.seller_application.reject', [$this->seller->id]), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.seller.index'))->assertForbidden();

    expect($this->seller->fresh()->status)->toBe(SellerStatus::PendingVerification);
});

test('an application cannot be approved twice or from the wrong state', function () {
    Notification::fake();
    $onboarding = Seller::factory()->onboarding()->create();

    $this->actingAs($this->admin)->post(route('admin.seller_application.approve', [$onboarding->id]))->assertSessionHas('error');
    expect($onboarding->fresh()->status)->toBe(SellerStatus::Onboarding);

    $this->actingAs($this->admin)->post(route('admin.seller_application.approve', [$this->seller->id]));
    $this->actingAs($this->admin)->post(route('admin.seller_application.approve', [$this->seller->id]))->assertSessionHas('error');
});

test('admins see KYC documents only through the authorised viewer', function () {
    Storage::fake('private');
    $document = SellerKycDocument::factory()->for($this->seller)->create(['path' => 'seller_kyc/'.$this->seller->id.'/cert.pdf']);
    Storage::disk('private')->put($document->path, '%PDF-1.4 test');

    $this->actingAs($this->admin)
        ->get(route('admin.seller.kyc_view', [$this->seller->id, $document->id]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs(sellerAdmin(['admin.seller.index']))
        ->get(route('admin.seller.kyc_view', [$this->seller->id, $document->id]))
        ->assertForbidden();

    // Never public: no /storage URL serves it.
    expect($this->get('/storage/'.$document->path)->status())->toBeIn([403, 404]);
});

test('an admin verifies a bank account in manual review', function () {
    $account = $this->seller->primaryBankAccount()->first();
    $account->update(['verification_status' => SellerBankAccount::STATUS_MANUAL_REVIEW, 'verified_at' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.seller.bank_verify_manual', [$this->seller->id, $account->id]), ['reason' => 'Cheque matches'])
        ->assertSessionHas('success');

    expect($account->fresh())
        ->verification_status->toBe(SellerBankAccount::STATUS_VERIFIED)
        ->verified_by->toBe($this->admin->id)
        ->verification_note->toBe('Cheque matches');
});

test('the sellers grid and edit form work', function () {
    $this->actingAs($this->admin)->get(route('admin.seller.index'))->assertSuccessful()->assertSee($this->seller->code);
    $this->actingAs($this->admin)->get(route('admin.seller.show', [$this->seller->id]))->assertSuccessful();
    $this->actingAs($this->admin)->get(route('admin.seller.edit', [$this->seller->id]))->assertSuccessful();

    $this->actingAs($this->admin)
        ->put(route('admin.seller.update', [$this->seller->id]), [
            'store_name' => 'Renamed Store',
            'legal_name' => 'Renamed Legal',
            'business_type' => 'llp',
            'email' => 'Ops@Example.test',
            'phone' => '9845012345',
        ])
        ->assertRedirect(route('admin.seller.show', [$this->seller->id]));

    expect($this->seller->fresh())
        ->store_name->toBe('Renamed Store')
        ->business_type->toBe('llp')
        ->email->toBe('ops@example.test')
        ->phone->toBe('+919845012345')
        ->status->toBe(SellerStatus::PendingVerification);
});
