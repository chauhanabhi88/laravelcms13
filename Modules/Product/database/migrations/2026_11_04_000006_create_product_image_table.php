<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('attribute_option_id')->nullable()->comment('Colour-specific image; null = general');
            $table->string('path')->comment('File name under storage/<product upload folder>');
            $table->integer('position')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('content_hash', 32)->nullable()->index()->comment('md5 of a 64x64 greyscale copy (duplicate detection)');
            $table->string('alt')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'position']);
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image');
    }
};
