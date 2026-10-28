<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Logistics';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable].
     */
    private const SCREENS = [
        'admin.shipment.index' => ['Shipments', 0, [
            ['id', 'ID', 1, 2, true],
            ['sub_order_no', 'Sub-order No', 2, 1, false],
            ['awb', 'AWB', 3, 1, true],
            ['carrier_name', 'Carrier', 4, 1, true],
            ['seller', 'Seller', 5, 1, false],
            ['status', 'Status', 6, 1, true],
            ['edd', 'EDD', 7, 1, true],
            ['attempts', 'Attempts', 8, 1, true],
            ['last_scan', 'Last Scan', 9, 1, false],
            ['cod_amount', 'COD Amount', 10, 1, true],
            ['payment_mode', 'Payment', 11, 2, true],
            ['created_at', 'Created At', 12, 1, true],
        ]],
        'admin.ndr.index' => ['NDR', 1, [
            ['id', 'ID', 1, 2, true],
            ['awb', 'AWB', 2, 1, false],
            ['sub_order_no', 'Sub-order No', 3, 1, false],
            ['reason', 'Reason', 4, 1, false],
            ['attempts', 'Attempts', 5, 1, true],
            ['customer_action', 'Action', 6, 1, true],
            ['status', 'Status', 7, 1, true],
            ['created_at', 'Raised At', 8, 1, true],
        ]],
        'admin.courier.index' => ['Couriers', 2, [
            ['id', 'ID', 1, 2, true],
            ['code', 'Code', 2, 1, true],
            ['name', 'Name', 3, 1, true],
            ['driver', 'Driver', 4, 1, true],
            ['supports_cod', 'COD', 5, 1, true],
            ['supports_reverse', 'Reverse', 6, 1, true],
            ['priority', 'Priority', 7, 1, true],
        ]],
        'admin.courier_rule.index' => ['Courier Rules', 3, [
            ['id', 'ID', 1, 2, true],
            ['priority', 'Priority', 2, 1, true],
            ['name', 'Name', 3, 1, true],
            ['courier', 'Courier', 4, 1, false],
            ['strategy', 'Strategy', 5, 1, true],
            ['conditions', 'Conditions', 6, 1, false],
        ]],
    ];

    /**
     * Top-level "Logistics" sidebar group with the shipments, NDR, couriers and rules screens,
     * their grid columns and the Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.shipment.index')->exists()) {
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
