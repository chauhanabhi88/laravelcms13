<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin.address.* routes (address list and delete on the customer edit page) now check
 * their permissions (plan M21 §16.2). Roles that could already edit customers keep the
 * address panel: they get both address permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['admin.address.get_address', 'admin.address.delete'];

    public function up(): void
    {
        foreach (DB::table('role')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true);

            if (! is_array($permissions) || ! in_array('admin.customer.edit', $permissions, true)) {
                continue;
            }

            $granted = array_values(array_unique(array_merge($permissions, self::PERMISSIONS)));

            if ($granted !== $permissions) {
                DB::table('role')->where('id', $role->id)->update(['permissions' => json_encode($granted)]);
            }
        }
    }

    /**
     * Nothing to undo: the permissions may have been granted before this migration ran.
     */
    public function down(): void {}
};
