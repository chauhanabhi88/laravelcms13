<?php

use Illuminate\Support\Facades\Storage;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Storage::fake('private');
    sellerTestPincode('560034');
    [$this->sellerA, $this->ownerA] = sellerWithOwner();
    [$this->sellerB, $this->ownerB] = sellerWithOwner();
    $this->warehouseB = SellerWarehouse::factory()->for($this->sellerB)->create(['name' => 'B Warehouse']);
});

test('seller A cannot open or change seller B\'s warehouse', function () {
    $this->actingAs($this->ownerA, 'seller');

    $this->get(route('seller.warehouse.edit', [$this->warehouseB->id]))->assertNotFound();
    $this->put(route('seller.warehouse.update', [$this->warehouseB->id]), [
        'name' => 'Hijacked',
        'contact_name' => 'Xavier',
        'contact_phone' => '9845012345',
        'address_line1' => 'Somewhere 123',
        'pincode' => '560034',
    ])->assertNotFound();
    $this->post(route('seller.warehouse.default', [$this->warehouseB->id]))->assertNotFound();
    $this->delete(route('seller.warehouse.destroy', [$this->warehouseB->id]))->assertNotFound();

    $this->get(route('seller.warehouse.index'))->assertSuccessful()->assertDontSee('B Warehouse');

    expect($this->warehouseB->fresh()->name)->toBe('B Warehouse');
});

test('seller A cannot retry verification of seller B\'s bank account', function () {
    $account = SellerBankAccount::factory()->for($this->sellerB)->create(['verification_status' => SellerBankAccount::STATUS_FAILED]);

    $this->actingAs($this->ownerA, 'seller')->post(route('seller.bank.verify', [$account->id]))->assertNotFound();
});

test('seller A cannot download seller B\'s KYC document', function () {
    $document = SellerKycDocument::factory()->for($this->sellerB)->create();
    Storage::disk('private')->put($document->path, '%PDF-1.4');

    $this->actingAs($this->ownerA, 'seller')->get(route('seller.kyc.download', [$document->id]))->assertNotFound();
    $this->actingAs($this->ownerB, 'seller')->get(route('seller.kyc.download', [$document->id]))->assertSuccessful();
});

test('seller A cannot edit or remove seller B\'s staff', function () {
    $staffB = SellerUser::factory()->for($this->sellerB)->role('orders')->create();

    $this->actingAs($this->ownerA, 'seller');
    $this->get(route('seller.staff.edit', [$staffB->id]))->assertNotFound();
    $this->delete(route('seller.staff.destroy', [$staffB->id]))->assertNotFound();

    expect($staffB->fresh())->not->toBeNull();
});

test('staff roles open only their own areas', function () {
    $orders = SellerUser::factory()->for($this->sellerA)->role('orders')->create();
    $finance = SellerUser::factory()->for($this->sellerA)->role('finance')->create();

    $this->actingAs($orders, 'seller');
    $this->get(route('seller.dashboard'))->assertSuccessful();
    $this->get(route('seller.warehouse.index'))->assertSuccessful();
    $this->get(route('seller.bank.index'))->assertForbidden();
    $this->get(route('seller.staff.index'))->assertForbidden();
    $this->get(route('seller.profile.edit'))->assertForbidden();

    $this->actingAs($finance, 'seller');
    $this->get(route('seller.bank.index'))->assertSuccessful();
    $this->get(route('seller.warehouse.index'))->assertForbidden();
});

test('a staff login disabled mid-session is signed out', function () {
    $staff = SellerUser::factory()->for($this->sellerA)->role('orders')->create();
    $this->actingAs($staff, 'seller')->get(route('seller.dashboard'))->assertSuccessful();

    $staff->update(['status' => config('core.disabled')]);

    $this->get(route('seller.dashboard'))->assertRedirect(route('seller.login'));
    $this->assertGuest('seller');
});
