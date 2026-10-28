<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily per-seller fulfilment counts (FR-FUL-008), written by the nightly snapshot job;
     * the supplier dashboard sums the last 30 days into rates. One row per seller and date.
     */
    public function up(): void
    {
        Schema::create('seller_quality_metric', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id');
            $table->date('date');
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('cancelled_by_seller')->default(0);
            $table->unsignedInteger('late_dispatch')->default(0);
            $table->unsignedInteger('returns')->default(0);
            $table->unsignedInteger('rto')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'date']);
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_quality_metric');
    }
};
