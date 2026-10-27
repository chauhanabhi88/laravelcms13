<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'core:prune-otp-codes';

    /**
     * Schedule the daily OTP clean-up through the Cron module (admin > Cron Jobs).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Prune OTP codes',
            'description' => 'Deletes one-time passwords older than 24 hours.',
            'command' => self::COMMAND,
            'cron_expression' => '15 3 * * *',
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
