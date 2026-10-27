<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DPDP data requests (FR-CUS-008). A deletion waits customer.deletion_grace_days (cancellable),
     * then the AnonymiseCustomer job clears the PII. "notes" records why a run was held back
     * (e.g. an open order).
     */
    public function up(): void
    {
        Schema::create('customer_data_request', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('customer_id');
            $table->string('type', 20)->comment('deletion | export');
            $table->string('status', 20)->default('pending')->comment('pending | processing | done | cancelled');
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['customer_id', 'type', 'status']);
            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_data_request');
    }
};
