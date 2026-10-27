<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable home page blocks, rendered in sort order while active and inside their
     * optional schedule window.
     */
    public function up(): void
    {
        Schema::create('home_section', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('type', 30)->comment('hero, trust_strip, category_tiles, banner_row, collection, products_for_you, html_block, blog_posts');
            $table->json('config')->nullable()->comment('{banner_group_code} | {category_ids[]} | {collection_id, limit} | {block_code} | {limit}');
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('home_section_translation', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('home_section_id');
            $table->string('locale', 10)->index();
            $table->string('title', 150)->nullable();
            $table->string('subtitle')->nullable();
            $table->timestamps();

            $table->unique(['home_section_id', 'locale']);
            $table->foreign('home_section_id')->references('id')->on('home_section')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_translation');
        Schema::dropIfExists('home_section');
    }
};
