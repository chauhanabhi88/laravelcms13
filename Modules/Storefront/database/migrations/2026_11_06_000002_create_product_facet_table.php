<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filterable options of each indexed product: specification options and the variant-axis
     * options of variants with stock available. Written by SearchIndexer.
     */
    public function up(): void
    {
        Schema::create('product_facet', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('attribute_id');
            $table->unsignedInteger('attribute_option_id');

            $table->primary(['product_id', 'attribute_id', 'attribute_option_id'], 'product_facet_primary');
            $table->index(['attribute_id', 'attribute_option_id', 'product_id'], 'product_facet_option_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_facet');
    }
};
