<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Reports';

    /**
     * Report screens: route (also the permission the sidebar checks) => [label, sort order].
     * None of them has a grid, so there are no columns rows.
     */
    private const SCREENS = [
        'admin.report.dashboard' => ['Analytics overview', 0],
        'admin.report.sales' => ['Sales', 1],
        'admin.report.fulfilment' => ['Fulfilment & RTO', 2],
        'admin.report.returns' => ['Returns', 3],
        'admin.report.sellers' => ['Seller leaderboard', 4],
        'admin.report.products' => ['Products & categories', 5],
        'admin.report.geography' => ['Geography', 6],
        'admin.report.payments' => ['Payment mix', 7],
        'admin.report.search' => ['Search', 8],
        'admin.report.export' => ['Exports', 9],
    ];

    /**
     * Top-level "Reports" sidebar group (plan M19 §6) and the Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.report.dashboard')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => self::GROUP_LABEL,
                'link' => null,
                'link_target' => 2,
                'sort_order' => 8,
                'is_system' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::SCREENS as $route => [$label, $sortOrder]) {
                DB::table('menu')->insert([
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
            }
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, $this->permissions()))));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
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
