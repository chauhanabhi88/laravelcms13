<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grid columns per admin screen: [code, name, sort order, shown by default, sortable].
     */
    private const GRIDS = [
        'admin.catalog_category.index' => ['Categories', 0, [
            ['id', 'ID', 1, 1, true],
            ['name', 'Name', 2, 1, false],
            ['slug', 'Slug', 3, 1, true],
            ['parent_id', 'Parent', 4, 1, false],
            ['depth', 'Level', 5, 1, true],
            ['is_leaf', 'Leaf', 6, 1, true],
            ['sort_order', 'Sort Order', 7, 1, true],
            ['show_in_menu', 'In Menu', 8, 2, true],
            ['updated_at', 'Updated At', 9, 2, true],
        ]],
        'admin.catalog_brand.index' => ['Brands', 1, [
            ['id', 'ID', 1, 1, true],
            ['logo', 'Logo', 2, 1, false],
            ['name', 'Name', 3, 1, false],
            ['slug', 'Slug', 4, 1, true],
            ['is_verified', 'Verified', 5, 1, true],
            ['status', 'Status', 6, 1, true],
            ['created_at', 'Created At', 7, 2, true],
        ]],
        'admin.catalog_size_chart.index' => ['Size Charts', 2, [
            ['id', 'ID', 1, 1, true],
            ['name', 'Name', 2, 1, true],
            ['unit', 'Unit', 3, 1, true],
            ['sizes', 'Sizes', 4, 1, false],
            ['updated_at', 'Updated At', 5, 2, true],
        ]],
    ];

    /**
     * Top-level "Catalog" sidebar group, one entry per grid, the grids' columns and the
     * Master Admin grant. Grids read their columns from the "columns" table keyed by the
     * menu row, so the screens render nothing without these rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.catalog_category.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => 'Catalog',
                'link' => null,
                'link_target' => 2,
                'sort_order' => 1,
                'is_system' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::GRIDS as $route => [$label, $sortOrder, $columns]) {
                $menuId = DB::table('menu')->insertGetId([
                    'parent_id' => $groupId,
                    'label' => $label,
                    'link' => $route,
                    'link_target' => 2,
                    'sort_order' => $sortOrder,
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
                    ], $columns));
                }
            }
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, $this->permissions()))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            // The columns rows cascade with their menu row.
            DB::table('menu')->whereIn('link', array_keys(self::GRIDS))->delete();
            DB::table('menu')->where('parent_id', 0)->where('label', 'Catalog')->whereNull('link')->delete();
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
