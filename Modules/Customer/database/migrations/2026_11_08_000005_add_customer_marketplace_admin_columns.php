<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROUTE = 'admin.customer.index';

    private const PERMISSIONS = ['admin.customer.toggle_cod_block'];

    /**
     * Admin customer grid (M08): phone, reseller, COD block and RTO columns, plus the
     * Master Admin grant for the new COD-block action. Grids read their columns from the
     * "columns" table keyed by the menu row. Safe to run on an empty schema.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && Schema::hasTable('columns')) {
            $menuId = DB::table('menu')->where('link', self::ROUTE)->value('id');

            if ($menuId !== null) {
                $now = now();
                $existing = DB::table('columns')->where('menu_id', $menuId)->pluck('code')->all();
                $columns = [
                    ['phone', 'Phone', 7, 1],
                    ['is_reseller', 'Reseller', 8, 1],
                    ['cod_blocked', 'COD Blocked', 9, 1],
                    ['rto_count', 'RTO Count', 10, 2],
                ];

                $rows = [];
                foreach ($columns as [$code, $name, $sort, $default]) {
                    if (! in_array($code, $existing, true)) {
                        $rows[] = [
                            'name' => $name,
                            'code' => $code,
                            'description' => $name,
                            'sort_order' => $sort,
                            'is_sortable' => 1,
                            'is_default' => $default,
                            'menu_id' => $menuId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('columns')->insert($rows);
                }
            }
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, self::PERMISSIONS))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu') && Schema::hasTable('columns')) {
            $menuId = DB::table('menu')->where('link', self::ROUTE)->value('id');

            if ($menuId !== null) {
                DB::table('columns')->where('menu_id', $menuId)->whereIn('code', ['phone', 'is_reseller', 'cod_blocked', 'rto_count'])->delete();
            }
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
