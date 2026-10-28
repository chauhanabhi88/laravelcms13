<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Courier scans (FR-LOG-006). status is our mapped status (null for unknown courier
     * statuses); the unique key drops a scan delivered twice (webhook + poll).
     */
    public function up(): void
    {
        Schema::create('shipment_event', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shipment_id');
            $table->string('status', 30)->nullable();
            $table->string('provider_status', 60);
            $table->string('provider_status_code', 20)->default('');
            $table->string('location', 120)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->json('raw')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('shipment_id')->references('id')->on('shipment')->cascadeOnDelete();
            $table->index(['shipment_id', 'occurred_at']);
            $table->unique(['shipment_id', 'provider_status_code', 'provider_status', 'occurred_at'], 'shipment_event_scan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_event');
    }
};
