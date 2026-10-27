<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable]; the search report is a plain page without grid columns.
     */
    private const SCREENS = [
        'admin.home_section.index' => ['Home Sections', 0, [
            ['id', 'ID', 1, 1, true],
            ['type', 'Type', 2, 1, true],
            ['title', 'Title', 3, 1, false],
            ['sort_order', 'Sort Order', 4, 1, true],
            ['schedule', 'Schedule', 5, 1, false],
            ['status', 'Status', 6, 1, true],
            ['updated_at', 'Updated At', 7, 2, true],
        ]],
        'admin.product_collection.index' => ['Collections', 1, [
            ['id', 'ID', 1, 1, true],
            ['banner_image', 'Banner', 2, 2, false],
            ['name', 'Name', 3, 1, false],
            ['slug', 'Slug', 4, 1, true],
            ['type', 'Type', 5, 1, true],
            ['sort', 'Sort', 6, 1, true],
            ['status', 'Status', 7, 1, true],
            ['updated_at', 'Updated At', 8, 2, true],
        ]],
        'admin.search_report.index' => ['Search Report', 2, []],
    ];

    /**
     * Top-level "Storefront" sidebar group with one entry per screen, the grids' columns and
     * the Master Admin grant. Grids read their columns from the "columns" table keyed by the
     * menu row, so they render nothing without these rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.home_section.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => 'Storefront',
                'link' => null,
                'link_target' => 2,
                'sort_order' => 3,
                'is_system' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::SCREENS as $route => [$label, $sortOrder, $columns]) {
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

                if ($columns !== [] && Schema::hasTable('columns')) {
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
            DB::table('menu')->whereIn('link', array_keys(self::SCREENS))->delete();
            DB::table('menu')->where('parent_id', 0)->where('label', 'Storefront')->whereNull('link')->delete();
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
