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
        'admin.seller_application.index' => ['Applications', 0, [
            ['id', 'ID', 1, 1, true],
            ['code', 'Code', 2, 1, true],
            ['store_name', 'Store', 3, 1, true],
            ['legal_name', 'Legal Name', 4, 1, true],
            ['phone', 'Phone', 5, 2, false],
            ['gst_status', 'GST', 6, 1, true],
            ['bank_status', 'Bank', 7, 1, false],
            ['status', 'Status', 8, 1, true],
            ['submitted_at', 'Submitted At', 9, 1, true],
        ]],
        'admin.seller.index' => ['Sellers', 1, [
            ['id', 'ID', 1, 1, true],
            ['code', 'Code', 2, 1, true],
            ['store_name', 'Store', 3, 1, true],
            ['legal_name', 'Legal Name', 4, 1, true],
            ['phone', 'Phone', 5, 1, false],
            ['email', 'Email', 6, 2, false],
            ['gst_status', 'GST', 7, 2, true],
            ['status', 'Status', 8, 1, true],
            ['verified_at', 'Verified At', 9, 2, true],
            ['created_at', 'Created At', 10, 1, true],
        ]],
    ];

    /**
     * Top-level "Sellers" sidebar group (KYC queue + all sellers), the grids' columns and
     * the Master Admin grant. Grids read their columns from the "columns" table keyed by
     * the menu row, so the screens render nothing without these rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.seller.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => 'Sellers',
                'link' => null,
                'link_target' => 2,
                'sort_order' => 2,
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
            DB::table('menu')->where('parent_id', 0)->where('label', 'Sellers')->whereNull('link')->delete();
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
