<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sellable SKU. Stock changes go through InventoryService, which locks the row and
     * writes inventory_movement; available = stock - reserved.
     */
    public function up(): void
    {
        Schema::create('product_variant', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('seller_id')->comment('Denormalised for seller queries');
            $table->string('seller_sku', 64);
            $table->string('sku', 32)->unique()->comment('System SKU {sellerCode}-{productId}-{n}');
            $table->string('combination_key', 100)->comment('Sorted attributeId:optionId|... ("default" without variant axes)');
            $table->decimal('mrp', 12, 2);
            $table->decimal('price', 12, 2)->comment('Selling price, GST inclusive');
            $table->integer('stock')->default(0)->comment('Physical, sellable');
            $table->integer('reserved')->default(0)->comment('Held by unpaid / undispatched orders');
            $table->integer('low_stock_threshold')->default(5);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->decimal('length_cm', 6, 1)->nullable();
            $table->decimal('breadth_cm', 6, 1)->nullable();
            $table->decimal('height_cm', 6, 1)->nullable();
            $table->string('barcode', 32)->nullable()->comment('EAN / UPC');
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['seller_id', 'seller_sku']);
            $table->unique(['product_id', 'combination_key']);
            $table->index(['product_id', 'status']);
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant');
    }
};
