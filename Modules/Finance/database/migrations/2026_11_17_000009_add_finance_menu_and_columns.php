<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Finance';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable]. The TCS report has no grid.
     */
    private const SCREENS = [
        'admin.payout_batch.index' => ['Payout Batches', 0, [
            ['id', 'ID', 1, 2, true],
            ['batch_no', 'Batch No', 2, 1, true],
            ['cycle_end', 'Cycle End', 3, 1, true],
            ['gateway', 'Gateway', 4, 1, false],
            ['sellers_count', 'Sellers', 5, 1, false],
            ['total_amount', 'Total', 6, 1, true],
            ['status', 'Status', 7, 1, true],
            ['created_at', 'Built At', 8, 1, true],
        ]],
        'admin.ledger.index' => ['Seller Ledger', 1, [
            ['id', 'ID', 1, 1, true],
            ['posted_at', 'Posted At', 2, 1, true],
            ['seller', 'Seller', 3, 1, false],
            ['order', 'Order', 4, 1, false],
            ['type', 'Type', 5, 1, true],
            ['description', 'Description', 6, 2, false],
            ['amount', 'Amount', 7, 1, true],
            ['tax_amount', 'GST', 8, 2, false],
            ['status', 'Status', 9, 1, true],
            ['eligible_on', 'Payable On', 10, 1, true],
        ]],
        'admin.payout_hold.index' => ['Payout Holds', 2, [
            ['id', 'ID', 1, 2, true],
            ['seller', 'Seller', 2, 1, false],
            ['reason', 'Reason', 3, 1, false],
            ['placed_at', 'Placed At', 4, 1, true],
            ['released_at', 'Released At', 5, 1, true],
        ]],
        'admin.commission_rule.index' => ['Commission Rules', 3, [
            ['id', 'ID', 1, 1, true],
            ['scope', 'Scope', 2, 1, true],
            ['target', 'Category / Seller', 3, 1, false],
            ['value', 'Commission', 4, 1, true],
            ['applies_to', 'Applies To', 5, 2, false],
            ['effective_from', 'From', 6, 1, true],
            ['effective_to', 'To', 7, 1, true],
            ['status', 'Status', 8, 1, true],
        ]],
        'admin.fee_rule.index' => ['Fee Rules', 4, [
            ['id', 'ID', 1, 1, true],
            ['code', 'Fee', 2, 1, true],
            ['scope', 'Scope', 3, 1, false],
            ['target', 'Category / Seller', 4, 2, false],
            ['value', 'Value', 5, 1, true],
            ['charged_to', 'Charged To', 6, 1, false],
            ['effective_from', 'From', 7, 1, true],
            ['status', 'Status', 8, 1, true],
        ]],
        'admin.tax_document.index' => ['Invoices & Credit Notes', 5, [
            ['id', 'ID', 1, 2, true],
            ['number', 'Number', 2, 1, true],
            ['type', 'Type', 3, 1, false],
            ['seller', 'Seller', 4, 1, false],
            ['document_date', 'Date', 5, 1, true],
            ['taxable_value', 'Taxable', 6, 1, false],
            ['tax', 'GST', 7, 1, false],
            ['total', 'Total', 8, 1, true],
        ]],
        'admin.tcs_report.index' => ['TCS Report', 6, []],
        'admin.cod_remittance.index' => ['COD Remittances', 7, [
            ['id', 'ID', 1, 2, true],
            ['remittance_ref', 'Reference', 2, 1, false],
            ['remittance_date', 'Date', 3, 1, true],
            ['utr', 'UTR', 4, 1, false],
            ['amount', 'Amount', 5, 1, true],
            ['lines_count', 'AWBs', 6, 1, false],
            ['mismatch_count', 'Mismatches', 7, 1, false],
            ['status', 'Status', 8, 1, true],
        ]],
        'admin.reseller_withdrawal.index' => ['Reseller Withdrawals', 8, [
            ['id', 'ID', 1, 2, true],
            ['withdrawal_no', 'Withdrawal No', 2, 1, false],
            ['customer', 'Reseller', 3, 1, false],
            ['amount', 'Amount', 4, 1, true],
            ['beneficiary_label', 'To', 5, 1, false],
            ['status', 'Status', 6, 1, true],
            ['utr', 'UTR', 7, 1, false],
            ['created_at', 'Requested At', 8, 1, true],
        ]],
    ];

    /**
     * Top-level "Finance" sidebar group (plan M17 §6, doc 02 §3), the Master Admin grant and a
     * "Finance" role holding every finance permission.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.payout_batch.index')->exists()) {
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

        if (Schema::hasTable('role') && ! DB::table('role')->where('slug', 'finance')->exists()) {
            DB::table('role')->insert([
                'name' => 'Finance',
                'slug' => 'finance',
                'permissions' => json_encode($this->permissions()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            // The columns rows cascade with their menu row.
            DB::table('menu')->whereIn('link', array_keys(self::SCREENS))->delete();
            DB::table('menu')->where('parent_id', 0)->where('label', self::GROUP_LABEL)->whereNull('link')->delete();
        }

        $this->grantMasterAdmin(fn (array $permissions): array => array_values(array_diff($permissions, $this->permissions())));

        if (Schema::hasTable('role')) {
            DB::table('role')->where('slug', 'finance')->delete();
        }
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
