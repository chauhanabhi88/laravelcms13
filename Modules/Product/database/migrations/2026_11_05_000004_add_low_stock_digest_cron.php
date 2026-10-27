<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'product:low-stock-digest';

    /**
     * The Cron module schedules commands from its "cron" table: run the seller low-stock
     * digest once a day.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Seller low-stock digest',
            'description' => 'Emails each supplier the variants that ran low on stock since the last digest.',
            'command' => self::COMMAND,
            'cron_expression' => '0 9 * * *',
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
