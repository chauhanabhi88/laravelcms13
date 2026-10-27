<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMAND = 'storefront:aggregate-search-terms';

    /**
     * The Cron module schedules commands from its "cron" table: aggregate the search log into
     * search_term_stat (and prune old log rows) every night.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron') || DB::table('cron')->where('command', self::COMMAND)->exists()) {
            return;
        }

        DB::table('cron')->insert([
            'title' => 'Aggregate search terms',
            'description' => 'Rolls the storefront search log up into trending and zero-result statistics, then prunes old log rows.',
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
