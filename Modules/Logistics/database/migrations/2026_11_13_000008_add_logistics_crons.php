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
        ['logistics:poll-tracking', 'Poll shipment tracking', 'Polling fallback for courier webhooks: re-reads the tracking of shipments still on the way that have not been synced for a few hours, and moves their seller orders.', '15 */2 * * *'],
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
