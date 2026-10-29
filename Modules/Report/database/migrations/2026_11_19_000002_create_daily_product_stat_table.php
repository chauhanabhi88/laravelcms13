<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily product aggregate (plan M19 §3.2): units, GMV and returned units of items placed on
     * the day; page views from product.view_count deltas (view_total is the snapshot the next
     * delta starts from; written only by the live "today" run).
     */
    public function up(): void
    {
        Schema::create('daily_product_stat', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->date('date');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('seller_id')->default(0);
            $table->unsignedInteger('units')->default(0);
            $table->decimal('gmv', 14, 2)->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('view_total')->nullable()->comment('product.view_count when the views were taken');
            $table->unsignedInteger('returns')->default(0)->comment('Returned units of the items placed this day');

            $table->primary(['date', 'product_id']);
            $table->index(['product_id', 'date']);
            $table->index(['seller_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_product_stat');
    }
};
