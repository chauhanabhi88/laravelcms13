<?php

use Illuminate\Support\Facades\DB;
use Modules\User\Models\User;

if (! function_exists('catalogAdmin')) {
    /**
     * An admin user whose role holds $permissions (null = every Catalog permission).
     *
     * @param  list<string>|null  $permissions
     */
    function catalogAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Catalog/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Catalog Manager',
            'slug' => 'catalog_manager_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Catalog Admin',
            'email' => uniqid('catalog-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
