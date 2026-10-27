<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalised listing / search row, one per live product per content locale. Written
     * only by Modules\Storefront\Services\Search\SearchIndexer.
     *
     * The FULLTEXT index uses InnoDB's default parser. Ops: set innodb_ft_min_token_size = 2
     * (and rebuild the index) to make two-letter words searchable; shorter queries fall back
     * to a LIKE on the name. InnoDB full-text sees only committed rows.
     */
    public function up(): void
    {
        Schema::create('product_search_index', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('product_id');
            $table->string('locale', 5);
            $table->text('document')->comment('name + keywords + brand + category path names + option names');
            $table->string('name', 200)->comment('Display name in suggestions');
            $table->unsignedInteger('catalog_category_id');
            $table->string('category_path', 255)->comment('Copy of catalog_category.path for descendant filters');
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('catalog_brand_id')->nullable();
            $table->decimal('min_price', 12, 2)->default(0);
            $table->decimal('min_mrp', 12, 2)->default(0);
            $table->unsignedTinyInteger('discount')->default(0)->comment('% off of the cheapest variant (as on the card)');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->tinyInteger('in_stock')->default(1)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('is_cod_allowed')->default(1)->comment('1 = Yes, 2 = No');
            $table->integer('popularity')->default(0)->comment('sold_count * 3 + view_count / 100 + rating_count');
            $table->timestamp('published_at')->nullable();
            $table->tinyInteger('is_visible')->default(1)->comment('1 = Yes, 2 = No: live and the seller active');

            $table->primary(['product_id', 'locale']);
            $table->index(['locale', 'is_visible', 'category_path'], 'psi_visible_category_index');
            $table->index(['locale', 'is_visible', 'min_price'], 'psi_visible_price_index');
            $table->index(['locale', 'is_visible', 'popularity'], 'psi_visible_popularity_index');
            $table->index(['locale', 'is_visible', 'published_at'], 'psi_visible_published_index');
            $table->index('seller_id', 'psi_seller_index');
            $table->index('catalog_brand_id', 'psi_brand_index');
            $table->fullText('document', 'psi_document_fulltext');
            $table->fullText('name', 'psi_name_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_index');
    }
};
