<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Helpful" votes (plan M16 §3.3): one per customer and review; product_review.helpful_count
     * holds the total.
     */
    public function up(): void
    {
        Schema::create('product_review_vote', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('product_review_id');
            $table->unsignedInteger('customer_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['product_review_id', 'customer_id']);
            $table->index('customer_id');
            $table->foreign('product_review_id')->references('id')->on('product_review')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_vote');
    }
};
