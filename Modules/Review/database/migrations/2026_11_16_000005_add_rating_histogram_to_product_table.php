<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Published ratings per star ({"5": 120, "4": 40, …}) next to rating_avg / rating_count
     * (plan M16 §3.5). Written only by ReviewAggregateService.
     */
    public function up(): void
    {
        if (Schema::hasTable('product') && ! Schema::hasColumn('product', 'rating_histogram')) {
            Schema::table('product', function (Blueprint $table) {
                $table->json('rating_histogram')->nullable()->after('rating_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product') && Schema::hasColumn('product', 'rating_histogram')) {
            Schema::table('product', function (Blueprint $table) {
                $table->dropColumn('rating_histogram');
            });
        }
    }
};
