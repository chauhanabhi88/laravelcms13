<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The variant-axis options that define a variant (e.g. Size = M, Colour = Blue).
     */
    public function up(): void
    {
        Schema::create('product_variant_attribute_value', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('variant_id');
            $table->unsignedInteger('attribute_id');
            $table->unsignedInteger('attribute_option_id');

            $table->primary(['variant_id', 'attribute_id']);
            $table->index(['attribute_id', 'attribute_option_id'], 'product_variant_attr_value_option_index');
            $table->foreign('variant_id')->references('id')->on('product_variant')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_value');
    }
};
