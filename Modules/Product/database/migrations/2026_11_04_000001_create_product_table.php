<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A seller's product (parent of the sellable variants). Status follows the Product
     * state machine (history in Core's status_history); the price / stock / image columns
     * are denormalised from the variants by ProductAggregateService.
     */
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('catalog_category_id')->comment('Leaf category');
            $table->unsignedInteger('catalog_brand_id')->nullable();
            $table->string('slug', 180)->unique()->comment('URL is /p/{slug}-{id}');
            $table->string('status', 20)->index()->comment('draft, pending_qc, live, rejected, paused, blocked');
            $table->tinyInteger('has_pending_changes')->default(2)->comment('1 = Yes, 2 = No: a live product has content edits awaiting QC');
            $table->json('pending_changes')->nullable()->comment('QC-sensitive edits of a live product, applied on approval');
            $table->timestamp('submitted_at')->nullable()->comment('Entered the QC queue (submission or pending changes)');
            $table->text('qc_notes')->nullable()->comment('Latest rejection / block reason, shown to the seller');
            $table->unsignedInteger('qc_reviewed_by')->nullable()->comment('Admin users.id');
            $table->timestamp('qc_reviewed_at')->nullable();
            $table->string('hsn_code', 8)->nullable()->comment('Defaults from the category');
            $table->decimal('gst_percent', 5, 2)->comment('Defaults from the category');
            $table->string('country_of_origin', 2)->default('IN');
            $table->json('manufacturer_details')->nullable()->comment('{name, address} (legal metrology)');
            $table->json('packer_details')->nullable();
            $table->json('importer_details')->nullable();
            $table->unsignedTinyInteger('return_window_days')->nullable()->comment('Override, at most the category value');
            $table->string('return_type', 20)->nullable()->comment('Override: return_exchange, exchange_only, none');
            $table->tinyInteger('is_cod_allowed')->default(1)->comment('1 = Yes, 2 = No');
            $table->string('search_keywords', 500)->nullable()->comment('Seller tags, lower-cased and comma separated');
            $table->string('primary_image')->nullable()->comment('Denormalised for cards');
            $table->decimal('min_price', 12, 2)->default(0)->comment('Denormalised from active variants');
            $table->decimal('max_price', 12, 2)->default(0);
            $table->decimal('min_mrp', 12, 2)->default(0)->comment('MRP of the cheapest active variant');
            $table->unsignedTinyInteger('max_discount_percent')->default(0);
            $table->integer('total_stock')->default(0)->comment('Sum of available (stock - reserved) of active variants');
            $table->decimal('rating_avg', 3, 2)->default(0)->comment('M16');
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->tinyInteger('seller_active')->default(1)->comment('1 = Yes, 2 = No: denormalised seller status for storefront filtering');
            $table->timestamp('published_at')->nullable()->comment('First time live');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'seller_active', 'catalog_category_id'], 'product_storefront_index');
            $table->index(['seller_id', 'status']);
            $table->index('catalog_category_id');
            $table->index('min_price');
            $table->index('published_at');
            $table->index('rating_avg');
            $table->index('max_discount_percent');
            $table->index(['has_pending_changes', 'submitted_at']);
            $table->fullText('search_keywords');

            $table->foreign('seller_id')->references('id')->on('seller');
            $table->foreign('catalog_category_id')->references('id')->on('catalog_category');
            $table->foreign('catalog_brand_id')->references('id')->on('catalog_brand')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
