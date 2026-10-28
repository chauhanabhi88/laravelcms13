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
        ['payment:expire-unpaid', 'Expire unpaid payments', 'Fails prepaid payments past their retry window (after a last gateway check) and cancels COD payments whose OTP was never verified.', '*/5 * * * *'],
        ['payment:reconcile', 'Reconcile Razorpay settlements', 'Matches yesterday\'s Razorpay settlement report against captured payments and refunds and reports mismatches to Finance.', '30 6 * * *'],
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
