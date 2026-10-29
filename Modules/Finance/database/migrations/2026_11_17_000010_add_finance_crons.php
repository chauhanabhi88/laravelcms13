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
        ['finance:release-eligible', 'Release payable seller entries', 'Makes seller ledger entries past their return window and payout delay payable (or held) and releases reseller margins.', '15 1 * * *'],
        ['finance:build-payouts', 'Build the payout batch', 'On the payout day (Settings > Finance) builds the draft payout batch for Finance to approve.', '30 2 * * *'],
        ['finance:fee-invoices', 'Issue monthly fee invoices', 'On the 1st, issues the platform fee invoices (commission and fees + GST) for the previous month.', '0 3 1 * *'],
        ['finance:import-cod-remittances', 'Import COD remittances', 'Imports the courier COD remittances and reconciles them with delivered COD shipments.', '0 6 * * *'],
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
