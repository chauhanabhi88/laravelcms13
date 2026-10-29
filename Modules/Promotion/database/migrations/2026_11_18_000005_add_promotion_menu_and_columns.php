<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Promotions';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable]. The report has no grid.
     */
    private const SCREENS = [
        'admin.coupon.index' => ['Coupons & offers', 0, [
            ['id', 'ID', 1, 1, true],
            ['code', 'Code', 2, 1, true],
            ['name', 'Name', 3, 1, false],
            ['type', 'Type', 4, 1, true],
            ['value', 'Value', 5, 1, true],
            ['scope', 'Scope', 6, 1, false],
            ['funded_by', 'Funded by', 7, 1, false],
            ['is_auto', 'Automatic', 8, 1, false],
            ['used_count', 'Used', 9, 1, true],
            ['starts_at', 'Starts', 10, 1, true],
            ['ends_at', 'Ends', 11, 1, true],
            ['status', 'Status', 12, 1, true],
            ['seller', 'Seller', 13, 2, false],
            ['batch', 'Batch', 14, 2, false],
            ['created_at', 'Created At', 15, 2, true],
        ]],
        'admin.flash_deal.index' => ['Flash deals', 1, [
            ['id', 'ID', 1, 1, true],
            ['name', 'Name', 2, 1, true],
            ['items_count', 'Variants', 3, 1, false],
            ['starts_at', 'Starts', 4, 1, true],
            ['ends_at', 'Ends', 5, 1, true],
            ['state', 'State', 6, 1, false],
            ['status', 'Status', 7, 1, true],
            ['created_at', 'Created At', 8, 2, true],
        ]],
        'admin.promotion_report.index' => ['Promotion report', 2, []],
    ];

    /**
     * Top-level "Promotions" sidebar group with coupons, flash deals and the report, their grid
     * columns and the Master Admin grant (plan M18 §6).
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.coupon.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => self::GROUP_LABEL,
                'link' => null,
                'link_target' => 2,
                'sort_order' => 7,
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
            DB::table('menu')->where('parent_id', 0)->where('label', self::GROUP_LABEL)->whereNull('link')->delete();
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
