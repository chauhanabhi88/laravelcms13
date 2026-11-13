<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'storefront:sitemap';

    /**
     * The Cron module schedules commands from its "cron" table: rebuild the XML sitemaps every
     * night (M21 E1).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Rebuild XML sitemaps',
            'description' => 'Writes the sitemap index and the page, category, product, store and collection sitemaps.',
            'command' => self::COMMAND,
            'cron_expression' => '30 2 * * *',
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
