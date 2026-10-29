<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Queued report exports (plan M19 §3.3): too large to stream, written by ExportReport to
     * the private disk and downloaded from Reports > Exports.
     */
    public function up(): void
    {
        Schema::create('report_export', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index()->comment('Admin users.id');
            $table->string('report', 30);
            $table->string('format', 5)->default('csv');
            $table->json('filters');
            $table->string('status', 12)->default('queued')->comment('queued, processing, ready, failed');
            $table->unsignedInteger('rows')->nullable();
            $table->string('path', 255)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export');
    }
};
