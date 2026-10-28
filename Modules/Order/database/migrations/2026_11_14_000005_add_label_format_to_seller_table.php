<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The seller's own label paper choice (supplier panel > Settings > Orders, plan M14 §6);
     * null follows admin > Settings > Order. auto_accept_orders already exists (M03).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('seller', 'label_format')) {
            Schema::table('seller', function (Blueprint $table) {
                $table->string('label_format', 20)->nullable()->after('auto_accept_orders')->comment('a4_4up | thermal_4x6, null = platform default');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seller', 'label_format')) {
            Schema::table('seller', function (Blueprint $table) {
                $table->dropColumn('label_format');
            });
        }
    }
};
