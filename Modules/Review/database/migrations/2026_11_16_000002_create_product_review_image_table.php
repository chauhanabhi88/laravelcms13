<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review photos (plan M16 §3.2): re-encoded JPEGs (EXIF stripped) and their thumbnails on
     * the public disk.
     */
    public function up(): void
    {
        Schema::create('product_review_image', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_review_id');
            $table->string('path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->foreign('product_review_id')->references('id')->on('product_review')->cascadeOnDelete();
            $table->index(['product_review_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_image');
    }
};
