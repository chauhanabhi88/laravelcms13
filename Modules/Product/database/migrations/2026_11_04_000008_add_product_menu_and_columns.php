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
        'admin.product.index' => ['All Products', 0, [
            ['id', 'ID', 1, 1, true],
            ['primary_image', 'Image', 2, 1, false],
            ['name', 'Name', 3, 1, false],
            ['seller', 'Seller', 4, 1, false],
            ['category', 'Category', 5, 1, false],
            ['price', 'Price', 6, 1, true],
            ['total_stock', 'Stock', 7, 1, true],
            ['status', 'Status', 8, 1, true],
            ['published_at', 'Published At', 9, 2, true],
            ['created_at', 'Created At', 10, 1, true],
            ['updated_at', 'Updated At', 11, 2, true],
        ]],
        'admin.product_qc.index' => ['QC Queue', 1, [
            ['id', 'ID', 1, 1, true],
            ['primary_image', 'Image', 2, 1, false],
            ['name', 'Name', 3, 1, false],
            ['seller', 'Seller', 4, 1, false],
            ['category', 'Category', 5, 1, false],
            ['price', 'Price', 6, 1, true],
            ['queue_type', 'Type', 7, 1, false],
            ['submitted_at', 'Waiting', 8, 1, true],
        ]],
        'admin.inventory_movement.index' => ['Inventory Movements', 2, [
            ['id', 'ID', 1, 1, true],
            ['created_at', 'Date', 2, 1, true],
            ['sku', 'SKU', 3, 1, false],
            ['product', 'Product', 4, 1, false],
            ['type', 'Type', 5, 1, true],
            ['bucket', 'Bucket', 6, 1, true],
            ['quantity', 'Quantity', 7, 1, true],
            ['stock_after', 'Stock After', 8, 1, false],
            ['reserved_after', 'Reserved After', 9, 1, false],
            ['reference', 'Reference', 10, 1, false],
            ['actor', 'Actor', 11, 2, false],
            ['note', 'Note', 12, 1, false],
        ]],
    ];

    /**
     * Top-level "Products" sidebar group (all products, QC queue, inventory movements), the
     * grids' columns and the Master Admin grant. Grids read their columns from the "columns"
     * table keyed by the menu row, so the screens render nothing without these rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.product.index')->exists()) {
            $now = now();

            $groupId = DB::table('menu')->insertGetId([
                'parent_id' => 0,
                'label' => 'Products',
                'link' => null,
                'link_target' => 2,
                'sort_order' => 3,
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
            DB::table('menu')->where('parent_id', 0)->where('label', 'Products')->whereNull('link')->delete();
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
