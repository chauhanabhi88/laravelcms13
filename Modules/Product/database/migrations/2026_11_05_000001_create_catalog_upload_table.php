<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier's bulk upload (new catalog or price & stock), processed by a queued job.
     */
    public function up(): void
    {
        Schema::create('catalog_upload', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('seller_user_id')->nullable()->comment('Uploader');
            $table->string('type', 20)->comment('new_catalog, price_stock');
            $table->unsignedInteger('catalog_category_id')->nullable()->comment('Template category of a new_catalog upload');
            $table->tinyInteger('save_as_draft')->default(2)->comment('1 = keep new products as drafts, 2 = submit them for QC');
            $table->string('file_path')->comment('Private disk');
            $table->string('original_name');
            $table->string('status', 30)->default('queued')->comment('queued, processing, completed, completed_with_errors, failed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('error_report_path')->nullable()->comment('Private disk: xlsx of the failed rows with an "Errors" column');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'id']);
            $table->index('status');
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->foreign('seller_user_id')->references('id')->on('seller_user')->nullOnDelete();
            $table->foreign('catalog_category_id')->references('id')->on('catalog_category')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_upload');
    }
};
