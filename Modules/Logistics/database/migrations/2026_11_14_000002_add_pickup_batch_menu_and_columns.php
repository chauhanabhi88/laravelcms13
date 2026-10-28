<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LINK = 'admin.pickup_batch.index';

    private const PERMISSIONS = ['admin.pickup_batch.index', 'admin.pickup_batch.filters', 'admin.pickup_batch.show'];

    /**
     * Grid columns: [code, name, sort order, shown by default, sortable].
     */
    private const COLUMNS = [
        ['id', 'ID', 1, 2, true],
        ['manifest_no', 'Manifest No', 2, 1, true],
        ['seller', 'Seller', 3, 1, false],
        ['carrier_name', 'Carrier', 4, 1, true],
        ['pickup_date', 'Pickup Date', 5, 1, true],
        ['shipments_count', 'Shipments', 6, 1, true],
        ['status', 'Status', 7, 1, true],
        ['created_at', 'Created At', 8, 1, true],
    ];

    /**
     * "Pickup batches" under the Logistics sidebar group (M13), its grid columns and the
     * Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', self::LINK)->exists()) {
            $now = now();
            $groupId = DB::table('menu')->where('parent_id', 0)->where('label', 'Logistics')->whereNull('link')->value('id');

            $menuId = DB::table('menu')->insertGetId([
                'parent_id' => $groupId ?? 0,
                'label' => 'Pickup Batches',
                'link' => self::LINK,
                'link_target' => 2,
                'sort_order' => 4,
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

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, self::PERMISSIONS))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            DB::table('menu')->where('link', self::LINK)->delete();
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_diff($permissions, self::PERMISSIONS)));
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
