<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROUTE = 'admin.cart.index';

    /**
     * Grid columns: [code, name, sort order, shown by default, sortable].
     */
    private const COLUMNS = [
        ['id', 'ID', 1, 1, true],
        ['customer', 'Customer', 2, 1, false],
        ['type', 'Type', 3, 1, true],
        ['items', 'Items', 4, 1, false],
        ['saved', 'Saved For Later', 5, 2, false],
        ['state', 'State', 6, 1, false],
        ['delivery_pincode', 'Pincode', 7, 2, false],
        ['last_activity_at', 'Last Activity', 8, 1, true],
        ['reminded_at', 'Reminded At', 9, 2, true],
        ['created_at', 'Created At', 10, 2, true],
    ];

    /**
     * "Carts" sidebar entry (read-only, for customer support), its grid columns and the
     * Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', self::ROUTE)->exists()) {
            $now = now();

            $menuId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => 'Carts',
                'link' => self::ROUTE,
                'link_target' => 2,
                'sort_order' => 5,
                'is_system' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (Schema::hasTable('columns')) {
                DB::table('columns')->insert(array_map(fn (array $column): array => [
                    'name' => $column[1],
                    'code' => $column[0],
                    'description' => $column[1],
                    'sort_order' => $column[2],
                    'is_sortable' => $column[4] ? 1 : 2,
                    'is_default' => $column[3],
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], self::COLUMNS));
            }
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, $this->permissions()))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            // The columns rows cascade with their menu row.
            DB::table('menu')->where('link', self::ROUTE)->delete();
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_diff($permissions, $this->permissions())));
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        $groups = require __DIR__.'/../../config/permissions.php';

        return array_merge(...array_map(fn (array $group): array => array_keys($group), array_values($groups)));
    }

    /**
     * @param  Closure(array<int, string>): array<int, string>  $change
     */
    private function grantMasterAdmin(Closure $change): void
    {
        if (! Schema::hasTable('role')) {
            return;
        }

        $role = DB::table('role')->where('slug', 'master_admin')->first(['id', 'permissions']);

        if ($role === null) {
            return;
        }

        $permissions = json_decode((string) $role->permissions, true) ?: [];

        DB::table('role')->where('id', $role->id)->update(['permissions' => json_encode($change($permissions))]);
    }
};
