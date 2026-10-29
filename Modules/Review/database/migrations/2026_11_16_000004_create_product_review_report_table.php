<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abuse reports (plan M16 §3.4): one per customer and review; product_review.report_count
     * holds the total and sends the review to the moderation queue at the threshold.
     */
    public function up(): void
    {
        Schema::create('product_review_report', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_review_id');
            $table->unsignedInteger('customer_id');
            $table->string('reason', 20)->comment('spam, offensive, irrelevant, fake');
            $table->string('comment', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_review_id', 'customer_id']);
            $table->foreign('product_review_id')->references('id')->on('product_review')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_report');
    }
};
