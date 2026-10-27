<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROUTE = 'admin.directory_pincode.index';

    private const PERMISSIONS = [
        'admin.directory_pincode.index',
        'admin.directory_pincode.filters',
        'admin.directory_pincode.edit',
    ];

    /**
     * Admin menu entry (next to Country), the grid's columns and the Master Admin grant.
     *
     * Grids read their columns from the "columns" table keyed by the menu row, so the
     * pincode grid renders nothing without these rows. Safe to run on an empty schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable('menu') || DB::table('menu')->where('link', self::ROUTE)->exists()) {
            return;
        }

        $now = now();
        $parentId = (int) DB::table('menu')->where('link', 'admin.country.index')->value('parent_id');

        $menuId = DB::table('menu')->insertGetId([
            'parent_id' => $parentId,
            'label' => 'Pincodes',
            'link' => self::ROUTE,
            'link_target' => 2,
            'sort_order' => 9,
            'is_system' => 2,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (Schema::hasTable('columns')) {
            $columns = [
                ['id', 'ID', 1, 1],
                ['pincode', 'Pincode', 2, 1],
                ['office_name', 'Office', 3, 1],
                ['district', 'District', 4, 1],
                ['state_id', 'State', 5, 1],
                ['city_id', 'City', 6, 2],
                ['is_serviceable', 'Serviceable', 7, 1],
                ['cod_allowed', 'COD', 8, 1],
                ['zone', 'Zone', 9, 1],
                ['updated_at', 'Updated At', 10, 2],
            ];

            DB::table('columns')->insert(array_map(fn (array $column): array => [
                'name' => $column[1],
                'code' => $column[0],
                'description' => $column[1],
                'sort_order' => $column[2],
                'is_sortable' => in_array($column[0], ['state_id', 'city_id'], true) ? 0 : 1,
                'is_default' => $column[3],
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $columns));
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, self::PERMISSIONS))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            // The columns rows cascade with the menu row.
            DB::table('menu')->where('link', self::ROUTE)->delete();
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
