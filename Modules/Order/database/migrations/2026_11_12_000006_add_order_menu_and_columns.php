<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Orders';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable].
     */
    private const SCREENS = [
        'admin.sales_order.index' => ['Orders', 0, [
            ['id', 'ID', 1, 2, true],
            ['order_no', 'Order No', 2, 1, true],
            ['customer', 'Customer', 3, 1, false],
            ['status', 'Status', 4, 1, true],
            ['payment_method', 'Payment', 5, 1, true],
            ['payment_status', 'Payment Status', 6, 1, true],
            ['items_count', 'Items', 7, 1, true],
            ['grand_total', 'Total', 8, 1, true],
            ['is_reseller_order', 'Reseller', 9, 2, true],
            ['pincode', 'Pincode', 10, 2, false],
            ['channel', 'Channel', 11, 2, true],
            ['placed_at', 'Placed At', 12, 1, true],
        ]],
        'admin.seller_order.index' => ['Seller Orders', 1, [
            ['id', 'ID', 1, 2, true],
            ['sub_order_no', 'Sub-order No', 2, 1, true],
            ['seller', 'Seller', 3, 1, false],
            ['status', 'Status', 4, 1, true],
            ['items_subtotal', 'Subtotal', 5, 1, true],
            ['tax_total', 'Tax', 6, 2, true],
            ['commission_total', 'Commission', 7, 2, true],
            ['sla_dispatch_by', 'Dispatch By', 8, 1, true],
            ['invoice_no', 'Invoice', 9, 2, false],
            ['created_at', 'Created At', 10, 1, true],
        ]],
    ];

    /**
     * Top-level "Orders" sidebar group with the orders and seller orders screens, their grid
     * columns and the Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.sales_order.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => self::GROUP_LABEL,
                'link' => null,
                'link_target' => 2,
                'sort_order' => 5,
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
