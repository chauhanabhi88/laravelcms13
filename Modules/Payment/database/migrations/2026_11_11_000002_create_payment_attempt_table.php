<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every gateway attempt on a payment, successful or not (FR-PAY-005).
     */
    public function up(): void
    {
        Schema::create('payment_attempt', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_id');
            $table->string('gateway_payment_id', 40)->nullable()->index();
            $table->string('status', 20)->comment('captured, failed, dismissed, mismatch, …');
            $table->string('source', 20)->default('callback')->comment('callback | webhook | expiry | api');
            $table->string('error_code', 60)->nullable();
            $table->string('error_description', 255)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('payment_id')->references('id')->on('payment')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempt');
    }
};
