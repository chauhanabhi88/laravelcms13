<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Ops status override on a seller order (plan M14 §6) for the Master Admin role.
     */
    private const PERMISSIONS = ['admin.seller_order.force_status'];

    public function up(): void
    {
        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_unique(array_merge($permissions, self::PERMISSIONS))));
    }

    public function down(): void
    {
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
