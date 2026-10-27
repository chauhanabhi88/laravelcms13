<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Category to Attribute mapping. Children inherit the rows of their ancestors; a row on
     * the child for the same attribute overrides the inherited one (CategoryAttributeResolver).
     */
    public function up(): void
    {
        Schema::create('catalog_category_attribute', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('catalog_category_id');
            $table->unsignedInteger('attribute_id');
            $table->tinyInteger('is_variant_axis')->default(2)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('is_filterable')->default(2)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('is_required')->default(2)->comment('1 = Yes, 2 = No; overrides attribute.is_required');
            $table->tinyInteger('show_on_card')->default(2)->comment('1 = Yes, 2 = No');
            $table->integer('sort_order')->default(0);
            $table->json('allowed_option_ids')->nullable()->comment('null = every option of the attribute');
            $table->timestamps();

            $table->unique(['catalog_category_id', 'attribute_id'], 'catalog_category_attribute_unique');
            $table->foreign('catalog_category_id')->references('id')->on('catalog_category')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attribute')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_category_attribute');
    }
};
