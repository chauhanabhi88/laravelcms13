<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Returns';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable].
     */
    private const SCREENS = [
        'admin.return_request.index' => ['Requests', 0, [
            ['id', 'ID', 1, 2, true],
            ['return_no', 'Return No', 2, 1, true],
            ['order', 'Order', 3, 1, false],
            ['seller', 'Seller', 4, 1, false],
            ['customer', 'Customer', 5, 1, false],
            ['type', 'Type', 6, 1, true],
            ['reason_code', 'Reason', 7, 1, false],
            ['status', 'Status', 8, 1, true],
            ['refund_amount', 'Refund', 9, 1, true],
            ['refund_status', 'Refund Status', 10, 2, false],
            ['auto_approved', 'Auto Approved', 11, 2, false],
            ['created_at', 'Created At', 12, 1, true],
        ]],
        'admin.return_claim.index' => ['Claims', 1, [
            ['id', 'ID', 1, 1, true],
            ['reference', 'Return / Order', 2, 1, false],
            ['source', 'Source', 3, 1, false],
            ['seller', 'Seller', 4, 1, false],
            ['reason', 'Reason', 5, 1, true],
            ['status', 'Status', 6, 1, true],
            ['resolution_amount', 'Credit', 7, 1, true],
            ['created_at', 'Raised At', 8, 1, true],
            ['decided_at', 'Decided At', 9, 2, true],
        ]],
    ];

    /**
     * Top-level "Returns" sidebar group with the returns console and the claims queue, their
     * grid columns and the Master Admin grant (plan M15 §6).
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.return_request.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => self::GROUP_LABEL,
                'link' => null,
                'link_target' => 2,
                'sort_order' => 6,
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
