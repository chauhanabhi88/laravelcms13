<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Specification (non-variant) attribute values: one row per option, or one text row.
     */
    public function up(): void
    {
        Schema::create('product_attribute_value', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('attribute_id');
            $table->unsignedInteger('attribute_option_id')->nullable();
            $table->string('value_text')->nullable();

            $table->index(['attribute_id', 'attribute_option_id'], 'product_attribute_value_option_index');
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_value');
    }
};
