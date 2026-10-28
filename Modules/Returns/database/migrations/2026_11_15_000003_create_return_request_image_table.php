<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer photos of a return (plan M15 §3.3): re-encoded images on the private disk.
     */
    public function up(): void
    {
        Schema::create('return_request_image', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('return_request_id');
            $table->string('path', 255);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('return_request_id')->references('id')->on('return_request')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_image');
    }
};
