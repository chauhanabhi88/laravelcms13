<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commands the Cron module schedules from its "cron" table: [command, title, description, expression].
     */
    private const CRONS = [
        ['order:auto-accept', 'Auto-accept seller orders', 'Accepts pending seller orders of suppliers who switched on auto-accept, and books their shipments.', '*/10 * * * *'],
        ['order:sla-warn', 'Warn of dispatch SLA breaches', 'Sends the supplier a one-time warning for orders not yet packed with 75% of their dispatch SLA used.', '5 * * * *'],
        ['order:sla-cancel', 'Cancel SLA-breached orders', 'Cancels seller orders not handed to the courier by their dispatch deadline plus the grace period, and refunds prepaid payments.', '20 * * * *'],
        ['order:quality-snapshot', 'Snapshot seller quality metrics', 'Writes the daily per-seller counts of orders, seller cancellations, late dispatches, returns and RTOs.', '30 1 * * *'],
        ['order:auto-confirm-receipts', 'Auto-confirm RTO receipts', 'Confirms RTO packages the supplier has not confirmed within the configured days as received in good condition.', '0 3 * * *'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cron')) {
            return;
        }

        foreach (self::CRONS as [$command, $title, $description, $expression]) {
            if (DB::table('cron')->where('command', $command)->exists()) {
                continue;
            }

            DB::table('cron')->insert([
                'title' => $title,
                'description' => $description,
                'command' => $command,
                'cron_expression' => $expression,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron')) {
            DB::table('cron')->whereIn('command', array_column(self::CRONS, 0))->delete();
        }
    }
};
