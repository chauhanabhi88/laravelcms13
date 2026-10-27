<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'storefront:flush-views';

    /**
     * The Cron module schedules commands from its "cron" table: write the product page views
     * collected in the cache to product.view_count every 10 minutes (M07).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Flush product page views',
            'description' => 'Adds the product page views counted in the cache to each product\'s view count.',
            'command' => self::COMMAND,
            'cron_expression' => '*/10 * * * *',
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
