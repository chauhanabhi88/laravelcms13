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
        ['order:cancel-abandoned', 'Cancel abandoned orders', 'Safety net for the payment hand-off: cancels orders still waiting for a payment or COD OTP whose payment has failed, been cancelled or is long past its window, and releases their stock.', '*/10 * * * *'],
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
