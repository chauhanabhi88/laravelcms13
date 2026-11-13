<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'core:monitor';

    /**
     * Check failed jobs and queue backlogs every 5 minutes through the Cron module (plan M21 §4).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Queue health alerts',
            'description' => 'Alerts on failed jobs (webhooks first) and queue backlogs above the threshold.',
            'command' => self::COMMAND,
            'cron_expression' => '*/5 * * * *',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cron')) {
            DB::table('cron')->where('command', self::COMMAND)->delete();
        }
    }
};
