<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Curated product sets for the home page and /collection/{slug}: rule based (filters on
     * the search index) or a manual list of product ids.
     */
    public function up(): void
    {
        Schema::create('product_collection', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('slug', 160)->unique();
            $table->string('type', 10)->default('rule')->comment('rule | manual');
            $table->json('rules')->nullable()->comment('{category_ids[], max_price, min_discount, min_rating, brand_ids[], seller_ids[]}');
            $table->json('product_ids')->nullable()->comment('Manual collections, in display order');
            $table->string('sort', 20)->default('popularity')->comment('popularity | new | price_asc | discount');
            $table->string('banner_image')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('product_collection_translation', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('product_collection_id');
            $table->string('locale', 10)->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['product_collection_id', 'locale'], 'product_collection_translation_unique');
            $table->foreign('product_collection_id')->references('id')->on('product_collection')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collection_translation');
        Schema::dropIfExists('product_collection');
    }
};
