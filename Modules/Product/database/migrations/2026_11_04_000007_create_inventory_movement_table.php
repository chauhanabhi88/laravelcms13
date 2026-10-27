<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only stock ledger written by InventoryService: one row per bucket change
     * with the stock / reserved snapshot after it.
     */
    public function up(): void
    {
        Schema::create('inventory_movement', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('variant_id');
            $table->unsignedInteger('product_id')->comment('Denormalised for the product detail screen');
            $table->string('type', 20)->comment('opening, adjust, reserve, release, commit, restock, correction');
            $table->integer('quantity')->comment('Signed change of the bucket');
            $table->string('bucket', 10)->comment('stock or reserved');
            $table->integer('stock_after');
            $table->integer('reserved_after');
            $table->string('reference_type', 150)->nullable()->comment('Morph class: cart, sales order item, return item, upload batch');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('actor_type', 20)->nullable()->comment('admin, seller, customer, system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'id']);
            $table->index(['product_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
            $table->foreign('variant_id')->references('id')->on('product_variant')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movement');
    }
};
