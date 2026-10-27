<?php

use Illuminate\Support\Facades\DB;
use Modules\Directory\Models\DirectoryPincode;
use Modules\User\Models\User;

/**
 * @param  list<string>  $permissions
 */
function pincodeAdmin(array $permissions = ['admin.directory_pincode.index', 'admin.directory_pincode.filters', 'admin.directory_pincode.edit']): User
{
    $roleId = DB::table('role')->insertGetId([
        'name' => 'Pincode Manager',
        'slug' => 'pincode_manager_'.uniqid(),
        'permissions' => json_encode($permissions),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::query()->forceCreate([
        'name' => 'Pincode Admin',
        'email' => uniqid('pincode-admin-').'@example.test',
        'password' => bcrypt('Secret#123'),
        'role_id' => $roleId,
        'status' => 1,
    ]);
}

test('guests are sent to the admin login', function () {
    $this->get(route('admin.directory_pincode.index'))->assertRedirect(route('backend_login'));
});

test('the grid needs the index permission', function () {
    $this->actingAs(pincodeAdmin(['admin.dashboard.index']))
        ->get(route('admin.directory_pincode.index'))
        ->assertForbidden();
});

test('the grid lists pincodes', function () {
    DirectoryPincode::factory()->create(['pincode' => '110001', 'office_name' => 'Connaught Place SO']);

    $this->actingAs(pincodeAdmin())
        ->get(route('admin.directory_pincode.index'))
        ->assertSuccessful()
        ->assertSee('Connaught Place SO');
});

test('toggling a flag applies it to every office of the pincode', function () {
    $office = DirectoryPincode::factory()->create(['pincode' => '110001']);
    $sibling = DirectoryPincode::factory()->create(['pincode' => '110001']);
    $other = DirectoryPincode::factory()->create(['pincode' => '560001']);

    $this->actingAs(pincodeAdmin())
        ->postJson(route('admin.directory_pincode.update_status'), ['id' => $office->id, 'field' => 'cod_allowed', 'status' => 2])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');

    expect($office->fresh()->cod_allowed)->toBe(2)
        ->and($sibling->fresh()->cod_allowed)->toBe(2)
        ->and($other->fresh()->cod_allowed)->toBe(1)
        ->and($office->fresh()->is_serviceable)->toBe(1);
});

test('only the serviceable and cod flags can be toggled', function () {
    $office = DirectoryPincode::factory()->create(['zone' => 'roi']);

    $this->actingAs(pincodeAdmin())
        ->postJson(route('admin.directory_pincode.update_status'), ['id' => $office->id, 'field' => 'pincode', 'status' => 2])
        ->assertSuccessful();

    expect($office->fresh()->pincode)->not->toBe('2');
});

test('the edit form saves office fields and pincode-level flags', function () {
    $office = DirectoryPincode::factory()->create(['pincode' => '110001', 'office_name' => 'Old Name']);
    $sibling = DirectoryPincode::factory()->create(['pincode' => '110001']);
    $admin = pincodeAdmin();

    $this->actingAs($admin)->get(route('admin.directory_pincode.edit', [$office->id]))->assertSuccessful()->assertSee('Old Name');

    $this->actingAs($admin)
        ->put(route('admin.directory_pincode.update', [$office->id]), ['directory_pincode' => [
            'office_name' => 'New Name SO',
            'district' => 'Central Delhi',
            'state_id' => '',
            'latitude' => '28.5',
            'longitude' => '77.2',
            'is_serviceable' => 2,
            'cod_allowed' => 2,
            'zone' => 'metro',
        ]])
        ->assertRedirect(route('admin.directory_pincode.index'))
        ->assertSessionHas('success');

    expect($office->fresh())
        ->office_name->toBe('New Name SO')
        ->district->toBe('Central Delhi')
        ->is_serviceable->toBe(2)
        ->zone->toBe('metro')
        ->and($sibling->fresh())
        ->office_name->not->toBe('New Name SO')
        ->is_serviceable->toBe(2)
        ->cod_allowed->toBe(2)
        ->zone->toBe('metro');
});

test('the edit form is validated', function () {
    $office = DirectoryPincode::factory()->create();

    $this->actingAs(pincodeAdmin())
        ->from(route('admin.directory_pincode.edit', [$office->id]))
        ->put(route('admin.directory_pincode.update', [$office->id]), ['directory_pincode' => ['office_name' => '', 'is_serviceable' => 5, 'zone' => 'mars']])
        ->assertSessionHasErrors(['directory_pincode.office_name', 'directory_pincode.district', 'directory_pincode.is_serviceable', 'directory_pincode.zone']);
});
