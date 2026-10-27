<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    sellerTestPincode('560034');
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
});

test('panel routes follow the seller route convention', function () {
    $dashboard = Route::getRoutes()->getByName('seller.dashboard');
    $wizard = Route::getRoutes()->getByName('seller.onboarding.show');

    expect($dashboard->uri())->toBe('supplier/dashboard')
        ->and($dashboard->gatherMiddleware())->toContain('web', 'auth:seller', 'seller.active')
        ->and($wizard->gatherMiddleware())->toContain('auth:seller')->not->toContain('seller.active');
});

test('the dashboard shows the KPI shell and the settings menu', function () {
    $this->get(route('seller.dashboard'))
        ->assertSuccessful()
        ->assertSee(trans('seller::panel.dashboard.kpis.orders_today'))
        ->assertSee(trans('seller::panel.menu.warehouses'))
        ->assertSee($this->seller->store_name);
});

test('the owner updates contact details and the logo', function () {
    Storage::fake('public');

    $this->get(route('seller.profile.edit'))->assertSuccessful()->assertSee($this->seller->maskedPan());

    $this->put(route('seller.profile.update'), [
        'email' => 'Orders@Store.test',
        'phone' => '9845012345',
        'auto_accept_orders' => config('core.yes'),
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ])->assertRedirect(route('seller.profile.edit'));

    $seller = $this->seller->fresh();
    expect($seller)
        ->email->toBe('orders@store.test')
        ->phone->toBe('+919845012345')
        ->auto_accept_orders->toBe(1)
        ->logo->not->toBeNull();

    Storage::disk('public')->assertExists($seller->logo);
});

test('warehouses: add, make default, and the default cannot be deleted', function () {
    $first = SellerWarehouse::factory()->for($this->seller)->create(['name' => 'First']);

    $this->post(route('seller.warehouse.store'), [
        'name' => 'Second',
        'contact_name' => 'Ravi Kumar',
        'contact_phone' => '9845012345',
        'address_line1' => '22, Industrial Area',
        'pincode' => '560034',
    ])->assertRedirect(route('seller.warehouse.index'));

    $second = SellerWarehouse::query()->where('seller_id', $this->seller->id)->where('name', 'Second')->sole();
    expect($second->is_default)->toBe(2);

    $this->post(route('seller.warehouse.default', [$second->id]))->assertRedirect(route('seller.warehouse.index'));
    expect($second->fresh()->is_default)->toBe(1)->and($first->fresh()->is_default)->toBe(2);

    $this->delete(route('seller.warehouse.destroy', [$second->id]))->assertSessionHas('error');
    $this->delete(route('seller.warehouse.destroy', [$first->id]))->assertSessionHas('success');

    expect(SellerWarehouse::query()->where('seller_id', $this->seller->id)->pluck('name')->all())->toBe(['Second']);
});

test('the owner manages staff logins', function () {
    $this->get(route('seller.staff.index'))->assertSuccessful();

    $this->post(route('seller.staff.store'), [
        'name' => 'Meera Nair',
        'email' => 'meera@store.test',
        'phone' => '9845011111',
        'role' => 'catalog',
        'status' => config('core.enabled'),
        'password' => 'Catalog#2026',
        'password_confirmation' => 'Catalog#2026',
    ])->assertRedirect(route('seller.staff.index'));

    $staff = SellerUser::query()->where('email', 'meera@store.test')->sole();
    expect($staff)->seller_id->toBe($this->seller->id)->role->toBe('catalog')->phone->toBe('+919845011111');

    $this->put(route('seller.staff.update', [$staff->id]), [
        'name' => 'Meera N',
        'email' => 'meera@store.test',
        'phone' => '9845011111',
        'role' => 'orders',
        'status' => config('core.disabled'),
    ])->assertRedirect(route('seller.staff.index'));

    expect($staff->fresh())->role->toBe('orders')->status->toBe(2);

    // Owners cannot be created, edited or removed here.
    $this->post(route('seller.staff.store'), ['name' => 'X', 'email' => 'x@store.test', 'phone' => '9845011112', 'role' => 'owner', 'status' => 1, 'password' => 'Catalog#2026', 'password_confirmation' => 'Catalog#2026'])
        ->assertSessionHasErrors('role');
    $this->get(route('seller.staff.edit', [$this->owner->id]))->assertNotFound();

    $this->delete(route('seller.staff.destroy', [$staff->id]))->assertRedirect(route('seller.staff.index'));
    expect(SellerUser::query()->find($staff->id))->toBeNull();
});

test('the landing page is public and sends signed-in suppliers to their panel', function () {
    Auth::guard('seller')->logout();

    $this->get(route('seller.landing'))
        ->assertSuccessful()
        ->assertSee(trans('seller::landing.hero.title'))
        ->assertSee(route('seller.register'));

    $this->actingAs($this->owner, 'seller')->get(route('seller.landing'))->assertRedirect(route('seller.dashboard'));
});

test('the store page shows active stores only', function () {
    $this->get(route('storefront.store.show', [$this->seller->store_slug]))
        ->assertSuccessful()
        ->assertSee($this->seller->store_name);

    [$pending] = sellerWithOwner('pendingVerification');
    $this->get(route('storefront.store.show', [$pending->store_slug]))->assertNotFound();
    $this->get('/s/no-such-store')->assertNotFound();
});
