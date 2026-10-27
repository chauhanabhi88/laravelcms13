<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
    sellerTestPincode('560034');
    [$this->seller, $this->owner] = sellerWithOwner();
    SellerBankAccount::factory()->for($this->seller)->verified()->create();
    $this->seller->update(['payout_hold' => config('core.no')]);
    $this->admin = sellerAdmin();
});

function newWarehouse(): array
{
    return [
        'name' => 'Second warehouse',
        'contact_name' => 'Ravi Kumar',
        'contact_phone' => '9845012345',
        'address_line1' => '22, Industrial Area',
        'pincode' => '560034',
    ];
}

test('suspension needs a reason, fires the event and holds payouts', function () {
    Event::fake([SellerSuspended::class]);

    $this->actingAs($this->admin)->post(route('admin.seller.suspend', [$this->seller->id]), ['reason' => ''])->assertSessionHas('error');
    expect($this->seller->fresh()->status)->toBe(SellerStatus::Active);

    $this->actingAs($this->admin)
        ->post(route('admin.seller.suspend', [$this->seller->id]), ['reason' => 'Repeated late dispatch'])
        ->assertRedirect(route('admin.seller.show', [$this->seller->id]));

    expect($this->seller->fresh())
        ->status->toBe(SellerStatus::Suspended)
        ->suspended_reason->toBe('Repeated late dispatch')
        ->payout_hold->toBe(1);

    Event::assertDispatched(SellerSuspended::class, fn ($event) => $event->seller->is($this->seller) && $event->admin->is($this->admin));
});

test('a suspended seller can read the panel but every write is refused', function () {
    $this->actingAs($this->admin)->post(route('admin.seller.suspend', [$this->seller->id]), ['reason' => 'Quality review']);

    $this->actingAs($this->owner, 'seller');

    $this->get(route('seller.dashboard'))->assertSuccessful()->assertSee('Quality review');
    $this->get(route('seller.warehouse.index'))->assertSuccessful();

    $this->post(route('seller.warehouse.store'), newWarehouse())->assertForbidden();
    $this->put(route('seller.profile.update'), ['email' => 'x@example.test', 'phone' => '9845012345'])->assertForbidden();
    $this->postJson(route('seller.warehouse.store'), newWarehouse())->assertForbidden()->assertJsonPath('success', false);

    expect(SellerWarehouse::query()->where('seller_id', $this->seller->id)->count())->toBe(0);
});

test('reactivation restores the panel and releases the payout hold', function () {
    Event::fake([SellerReactivated::class]);
    $this->actingAs($this->admin)->post(route('admin.seller.suspend', [$this->seller->id]), ['reason' => 'Quality review']);

    $this->actingAs($this->admin)->post(route('admin.seller.reactivate', [$this->seller->id]))->assertSessionHas('success');

    expect($this->seller->fresh())
        ->status->toBe(SellerStatus::Active)
        ->suspended_reason->toBeNull()
        ->payout_hold->toBe(2);

    Event::assertDispatched(SellerReactivated::class);

    $this->actingAs($this->owner, 'seller')->post(route('seller.warehouse.store'), newWarehouse())->assertRedirect(route('seller.warehouse.index'));
});

test('only active sellers can be suspended and only suspended ones reactivated', function () {
    $this->actingAs($this->admin)->post(route('admin.seller.reactivate', [$this->seller->id]))->assertSessionHas('error');

    [$pending] = sellerWithOwner('pendingVerification');
    $this->actingAs($this->admin)->post(route('admin.seller.suspend', [$pending->id]), ['reason' => 'x'])->assertSessionHas('error');

    expect($pending->fresh()->status)->toBe(SellerStatus::PendingVerification);
});
