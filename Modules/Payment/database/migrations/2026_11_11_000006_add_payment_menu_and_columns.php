<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_LABEL = 'Payments';

    /**
     * Admin screens: [label, sort order, grid columns]. A column is [code, name, sort order,
     * shown by default, sortable].
     */
    private const SCREENS = [
        'admin.payment.index' => ['Transactions', 0, [
            ['id', 'ID', 1, 1, true],
            ['order_no', 'Order', 2, 1, true],
            ['customer', 'Customer', 3, 1, false],
            ['method', 'Method', 4, 1, true],
            ['status', 'Status', 5, 1, true],
            ['amount', 'Amount', 6, 1, true],
            ['amount_refunded', 'Refunded', 7, 2, true],
            ['instrument', 'Instrument', 8, 1, false],
            ['gateway_payment_id', 'Gateway Payment', 9, 2, false],
            ['captured_at', 'Captured At', 10, 2, true],
            ['created_at', 'Created At', 11, 1, true],
        ]],
        'admin.payment_refund.index' => ['Refunds', 1, [
            ['id', 'ID', 1, 1, true],
            ['payment', 'Payment', 2, 1, false],
            ['amount', 'Amount', 3, 1, true],
            ['mode', 'Mode', 4, 1, true],
            ['status', 'Status', 5, 1, true],
            ['reference', 'Reference', 6, 1, false],
            ['reason', 'Reason', 7, 2, false],
            ['failure_reason', 'Failure', 8, 2, false],
            ['processed_at', 'Processed At', 9, 2, true],
            ['created_at', 'Created At', 10, 1, true],
        ]],
        'admin.webhook_event.index' => ['Webhooks', 2, [
            ['id', 'ID', 1, 1, true],
            ['provider', 'Provider', 2, 1, true],
            ['event_type', 'Event', 3, 1, true],
            ['event_id', 'Event ID', 4, 2, false],
            ['signature_valid', 'Signature', 5, 1, true],
            ['status', 'Status', 6, 1, true],
            ['attempts', 'Attempts', 7, 2, true],
            ['error', 'Error', 8, 2, false],
            ['created_at', 'Received At', 9, 1, true],
            ['processed_at', 'Processed At', 10, 2, true],
        ]],
        'admin.payment_recon.index' => ['Reconciliation', 3, [
            ['id', 'ID', 1, 1, true],
            ['settlement_date', 'Settlement Date', 2, 1, true],
            ['entity_type', 'Type', 3, 1, true],
            ['entity_id', 'Gateway ID', 4, 1, false],
            ['issue', 'Issue', 5, 1, true],
            ['gateway_amount', 'Gateway Amount', 6, 1, true],
            ['local_amount', 'Our Amount', 7, 1, false],
            ['state', 'State', 8, 1, false],
            ['created_at', 'Found At', 9, 2, true],
        ]],
    ];

    /**
     * Top-level "Payments" sidebar group with the transactions, refunds, webhooks and
     * reconciliation screens, their grid columns and the Master Admin grant.
     */
    public function up(): void
    {
        if (Schema::hasTable('menu') && ! DB::table('menu')->where('link', 'admin.payment.index')->exists()) {
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
