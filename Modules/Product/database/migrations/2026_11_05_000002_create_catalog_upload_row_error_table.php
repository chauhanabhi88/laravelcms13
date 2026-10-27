<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-row problems of a bulk upload: errors (the row was not saved) and warnings
     * (saved, but e.g. a product column differed from the group's first row).
     */
    public function up(): void
    {
        Schema::create('catalog_upload_row_error', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('catalog_upload_id');
            $table->unsignedInteger('row_number')->comment('Spreadsheet row; 0 = the whole file');
            $table->string('column', 60)->nullable()->comment('Template column key');
            $table->string('level', 10)->default('error')->comment('error, warning');
            $table->string('message', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['catalog_upload_id', 'row_number'], 'catalog_upload_row_error_upload_row_index');
            $table->foreign('catalog_upload_id')->references('id')->on('catalog_upload')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_upload_row_error');
    }
};
