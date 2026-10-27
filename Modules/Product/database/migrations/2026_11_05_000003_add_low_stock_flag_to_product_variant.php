<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when a variant's available stock falls to its threshold (VariantLowStock); the
     * daily low-stock digest lists the flagged variants and clears the flag.
     */
    public function up(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->timestamp('low_stock_flagged_at')->nullable()->after('low_stock_threshold');
            $table->index('low_stock_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropIndex(['low_stock_flagged_at']);
            $table->dropColumn('low_stock_flagged_at');
        });
    }
};
