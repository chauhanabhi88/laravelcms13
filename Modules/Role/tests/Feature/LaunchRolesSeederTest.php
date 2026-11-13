<?php

use Illuminate\Support\Facades\DB;
use Modules\Role\Database\Seeders\LaunchRolesSeeder;

/*
| Plan M21 §9: the operations roles seeded for launch.
*/

beforeEach(function () {
    DB::table('role')->whereIn('slug', array_keys(LaunchRolesSeeder::ROLES))->delete();
});

/**
 * @return list<string>
 */
function launchRolePermissions(string $slug): array
{
    return json_decode((string) DB::table('role')->where('slug', $slug)->value('permissions'), true);
}

test('every operations role is created with its permission groups', function () {
    $this->seed(LaunchRolesSeeder::class);

    expect(DB::table('role')->whereIn('slug', array_keys(LaunchRolesSeeder::ROLES))->count())->toBe(5)
        ->and(launchRolePermissions('catalog_moderator'))->toContain('admin.product_qc.index', 'admin.catalog_category.index', 'admin.dashboard.index')
        ->and(launchRolePermissions('finance'))->toContain('admin.payout_batch.index', 'admin.tcs_report.index')
        ->and(launchRolePermissions('logistics_ops'))->toContain('admin.courier.index', 'admin.ndr.index');
});

test('roles stay within their groups', function () {
    $this->seed(LaunchRolesSeeder::class);

    $catalog = launchRolePermissions('catalog_moderator');

    expect(collect($catalog)->contains(fn (string $key): bool => str_starts_with($key, 'admin.payout_batch.') || str_starts_with($key, 'admin.role.') || str_starts_with($key, 'admin.user.index')))->toBeFalse()
        ->and(launchRolePermissions('customer_support'))->not->toContain('admin.settings.index');
});

test('every seeded permission is declared by a module', function () {
    $available = LaunchRolesSeeder::availablePermissions();

    foreach (LaunchRolesSeeder::ROLES as $role) {
        expect(array_diff(LaunchRolesSeeder::permissionsFor($role['groups'], $available), $available))->toBe([]);
    }
});

test('an existing role is never overwritten', function () {
    DB::table('role')->insert(['name' => 'Finance', 'slug' => 'finance', 'permissions' => json_encode(['admin.dashboard.index']), 'created_at' => now(), 'updated_at' => now()]);

    $this->seed(LaunchRolesSeeder::class);
    $this->seed(LaunchRolesSeeder::class);

    expect(launchRolePermissions('finance'))->toBe(['admin.dashboard.index'])
        ->and(DB::table('role')->where('slug', 'seller_ops')->count())->toBe(1);
});
