<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A seller's "Generate labels" run (FR-FUL-004): the selected seller orders, the queued
     * job's state and the downloadable file (packing slips PDF, or a ZIP with the courier
     * label PDFs) on the private disk.
     */
    public function up(): void
    {
        Schema::create('label_batch', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id')->index();
            $table->unsignedInteger('created_by')->nullable()->comment('seller_user id');
            $table->string('status', 20)->default('queued')->comment('queued | processing | ready | failed');
            $table->json('seller_order_ids');
            $table->unsignedSmallInteger('orders_count')->default(0);
            $table->string('label_format', 20)->comment('a4_4up | thermal_4x6');
            $table->string('path', 255)->nullable()->comment('Private disk');
            $table->string('file_name', 120)->nullable();
            $table->json('provider_labels')->nullable()->comment('Courier label URLs');
            $table->string('error', 500)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_batch');
    }
};
