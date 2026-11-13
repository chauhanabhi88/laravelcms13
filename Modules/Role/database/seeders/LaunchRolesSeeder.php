<?php

declare(strict_types=1);

namespace Modules\Role\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace operations roles for launch (plan M21 §9, SRS §2.2 U7-U11).
 *
 * Each role gets every permission of the listed permission groups (the second segment of
 * "admin.<group>.<action>", read from each module's config/permissions.php), plus the dashboard
 * and the admin's own profile.
 *
 * Idempotent: a role whose slug already exists is left untouched, so permissions edited in the
 * admin are never overwritten.
 *
 *   php artisan db:seed --class="Modules\Role\Database\Seeders\LaunchRolesSeeder"
 */
class LaunchRolesSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, groups: list<string>}>
     */
    public const ROLES = [
        'catalog_moderator' => [
            'name' => 'Catalog Moderator',
            'groups' => ['catalog_category', 'catalog_brand', 'catalog_size_chart', 'attribute', 'product', 'product_qc', 'inventory_movement', 'product_review', 'home_section', 'product_collection', 'search_report'],
        ],
        'seller_ops' => [
            'name' => 'Seller Ops',
            'groups' => ['seller_application', 'seller', 'seller_order', 'return_claim', 'payout_hold'],
        ],
        'finance' => [
            'name' => 'Finance',
            'groups' => ['commission_rule', 'fee_rule', 'ledger', 'payout_batch', 'seller_payout', 'payout_hold', 'tcs_report', 'cod_remittance', 'reseller_withdrawal', 'tax_document', 'payment', 'payment_refund', 'payment_recon', 'report'],
        ],
        'customer_support' => [
            'name' => 'Customer Support',
            'groups' => ['sales_order', 'seller_order', 'return_request', 'customer', 'address', 'customerLog', 'payment', 'payment_refund', 'product_review', 'notification_log'],
        ],
        'logistics_ops' => [
            'name' => 'Logistics Ops',
            'groups' => ['courier', 'courier_rule', 'shipment', 'ndr', 'pickup_batch', 'directory_pincode', 'seller_order', 'return_request'],
        ],
    ];

    /**
     * Granted to every operations role.
     */
    public const COMMON = ['admin.dashboard.index', 'admin.user.editProfile', 'admin.user.updateProfile'];

    public function run(): void
    {
        $available = self::availablePermissions();

        foreach (self::ROLES as $slug => $role) {
            if (DB::table('role')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('role')->insert([
                'name' => $role['name'],
                'slug' => $slug,
                'permissions' => json_encode(self::permissionsFor($role['groups'], $available)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<string>  $groups
     * @param  list<string>  $available
     * @return list<string>
     */
    public static function permissionsFor(array $groups, array $available): array
    {
        $permissions = array_filter($available, fn (string $key): bool => in_array(explode('.', $key)[1] ?? '', $groups, true));

        return array_values(array_unique(array_merge(
            array_values(array_intersect(self::COMMON, $available)),
            array_values($permissions),
        )));
    }

    /**
     * Every permission key declared by an installed module.
     *
     * @return list<string>
     */
    public static function availablePermissions(): array
    {
        $keys = [];

        foreach (glob(base_path('Modules/*/config/permissions.php')) ?: [] as $file) {
            foreach ((array) require $file as $group) {
                $keys = array_merge($keys, array_keys((array) $group));
            }
        }

        return array_values(array_unique($keys));
    }
}
